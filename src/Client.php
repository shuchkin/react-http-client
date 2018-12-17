<?php

namespace Shuchkin\ReactHTTP;

class Client extends \Evenement\EventEmitter {
	public $headers;
	public $content;
	public $contentLength;
	public $cookie;
	public $url;
	public $maxRedirects;
	public $numRedirects;
	private $loop;
	private $connector;
	/* @var \React\Socket\ConnectionInterface */
	private $connection;
	/* @var \React\Promise\Deferred $deffered */
	private $deffered;
	private $busy;
	private $buffer;
	private $chunked;
	private $curChunkLen;
	private $curChunk;

	public $keepAlive;
	public function __construct( \React\EventLoop\LoopInterface $loop, array $options = [] ) {

		$options = array_merge([
			'timeout' => false,
		], $options );

		$this->loop         = $loop;
		$this->connector    = new \React\Socket\Connector( $this->loop, $options );
		$this->maxRedirects = 10;
		$this->numRedirects = 0;
	}

	public function get( $url, array $headers = [] ) {
		return $this->request( 'GET', $url, null, $headers );
	}
	public function post( $url, $data, array $headers = [] ) {
		return $this->request( 'POST', $url, $data, $headers );
	}

	public function request( $method, $url, $data = null, array $headers = [] ) {
		if ( $this->busy ) {
			$new_client = new self( $this->loop );
			$new_client->cookie = $this->cookie;
			$new_client->url = $this->url;

			return $new_client->request( $method, $url, $data, $headers );
		}
		if ( isset($this->listeners['debug'])) {
			$this->emit( 'debug', [$method.' '.$url.' data='.gettype($data).' headers='.count($headers)] );
		}
		if ( $method === 'REDIRECT' ) {
			$method = 'GET';
		} else {
			$this->deffered = new \React\Promise\Deferred();
		}
		$this->buffer        = '';
		$this->headers       = [];
		$this->content       = false;
		$this->contentLength = false;
		$this->chunked       = false;
		$this->curChunkLen   = false;
		$this->curChunk      = false;
		$this->busy          = true;

		$parts = $this->parseURL( $url );

		if ( !$parts['host'] ) {
			return new \React\Promise\RejectedPromise( new \Exception( 'Bad URL ' . $url ) );
		}
		if ( $this->url && $this->connection ) {

			$old = $this->parseURL( $this->url );

			if ( $parts['host'] !== $old['host'] || $parts['port'] !== $old['port'] ) {

				$this->connection->close();
				$this->connection = null;
				$this->cookie     = [];
			}
		}

		$this->url = $url;

		if ( !isset( $headers['Host'])) {
			$headers['Host'] = $parts['host'];
		}

		$connect_uri = $parts['scheme'] === 'https' ? 'tls://' : 'tcp://';
		$connect_uri .= $parts['host'] .':' . $parts['port'];

		$post = '';
		if ( $data === null ) {
			$content_type = null;
		} else if ( \is_array( $data ) ) {
			$post         = http_build_query( $data );
			$content_type = 'application/x-www-form-urlencoded';
		} else {
			$post = $data;
			if ( strpos( $post, '<?xml' ) === 0 ) {
				$content_type = 'text/xml';
			} else if ( strpos( $post, '{' ) === 0 ) {
				$content_type = 'application/json';
			} else {
				$content_type = 'text/html';
			}
		}

		if ( $content_type ) {
			$headers['Content-Type']   = $content_type;
			$headers['Content-Length'] = mb_strlen( $post, 'latin1' );
		}
		$request = strtoupper( $method ) . ' ' . $parts['path'] . ( !empty( $parts['query'] ) ? '?' . $parts['query'] : '' ) . " HTTP/1.1\r\n";

		foreach ( $headers as $k => $v ) {
			$request .= $k . ': ' . $v . "\r\n";
		}

		$request .= "Connection: close\r\n\r\n";

		if ( $content_type ) {
			$request .= $post;
		}

		if ( isset($this->listeners['debug'])) {
			$this->emit('debug', ['Host: '.$connect_uri."\r\n".$request] );
		}
		// keep alive?
		if ( $this->connection && $this->connection->isReadable() ) {
			$this->connection->write( $request );
		} else {
			$con_promise = $this->connector->connect( $connect_uri );
			if ( $con_promise ) {
				$con_promise->then(
					function ( \React\Socket\ConnectionInterface $connection ) use ( $request ) {
						if ( isset( $this->listeners['debug'] ) ) {
							$this->emit( 'debug', [ 'Connected to ' . $connection->getRemoteAddress() ] );
						}
						$this->connection = $connection;
						$this->connection->write( $request );
						$this->connection->on( 'data', [ $this, 'handleData' ] );
						$this->connection->on( 'end', [ $this, 'handleEnd' ] );
						$this->connection->on( 'close', [ $this, 'handleClose' ] );
						$this->connection->on( 'error', [ $this, 'handleError' ] );
					},
					function ( \Exception $ex ) {
						$this->deffered->reject( $ex );
					}
				);
			}
		}

		return $this->deffered->promise();
	}

	public function handleData( $data ) {

		if ( isset($this->listeners['debug'])) {
			$this->emit('debug', [ $data ] );
		}
		$this->buffer .= $data;

		// Read headers
		if ( $this->content === false ) {
			while ( false !== $pos = strpos( $this->buffer, "\r\n" ) ) {

				$line         = substr( $this->buffer, 0, $pos );
				$this->buffer = substr( $this->buffer, $pos + 2 );

				// read headers
				if ( $this->content === false ) {

					if ( preg_match( '/^HTTP\/1.[01]\s(\d+)\s(.*)/i', $line, $m ) ) {
						$this->headers['STATUS-CODE'] = $m[1];
						$this->headers['STATUS']      = $m[2];
					} else if ( $p = strpos( $line, ':' ) ) {
						$header                   = substr( $line, 0, $p );
						$header                   = strtoupper( $header ); // CONTENT-LeNgTH -> CONTENT-LENGTH
						$this->headers[ $header ] = trim( substr( $line, $p + 1 ) );
					} else if ( $line === '' ) { // body

						$this->contentLength = isset( $this->headers['CONTENT-LENGTH'] ) ? $this->headers['CONTENT-LENGTH'] : false;
						$this->chunked       = isset( $this->headers['TRANSFER-ENCODING'] ) && ( stripos( $this->headers['TRANSFER-ENCODING'], 'chunked' ) !== false );
						if ( isset( $this->headers['SET-COOKIE'] )
						     && preg_match_all( '/([^=]+)=([^;]+);/', $this->headers['SET-COOKIE'], $m ) ) {

							foreach ( $m[0] as $k => $v ) {
								$this->cookie[ $m[1][ $k ] ] = $m[2][ $k ];
							}
						}

						$this->content = '';
						break;
					}
				}
			}
		}

		if ( $this->content !== false ) {
			if ( $this->chunked ) {
				while ( false !== $pos = strpos( $this->buffer, "\r\n" ) ) {

					$line         = substr( $this->buffer, 0, $pos );
					$this->buffer = substr( $this->buffer, $pos + 2 );
					if ( $this->curChunkLen === false ) {
						//					echo "\r\nFIRST=$line";
						$this->curChunkLen = hexdec( $line );
						$this->curChunk    = '';
					} else if ( $line === '' && $this->curChunkLen === 0 ) {
						$this->handleEnd();
					} else {
						$this->curChunk .= $line;
						//					echo "\r\nLEN=" . \strlen( $this->curChunk ) . ' curChunkLen=' . $this->curChunkLen;
						if ( \strlen( $this->curChunk ) === $this->curChunkLen ) {
							if ( isset( $this->listeners['chunk'] ) ) {
								$this->emit( 'chunk', [ $this->curChunk, $this ] );
							} else {
								$this->content .= $this->curChunk;
							}
							$this->curChunkLen = false;
						} else {
							$this->curChunk .= "\r\n";
						}
					}
				}
			}

			if ( $this->contentLength
			     && \strlen( $this->buffer ) >= $this->contentLength ) {
				$this->content = $this->buffer;
				$this->buffer  = '';
				$this->handleEnd();
			}
		}
	}

	public function handleEnd() {
		$this->busy = false;

		if ( isset( $this->headers['CONNECTION'] ) && strtoupper( $this->headers['CONNECTION']) === 'CLOSE' ) {
			$this->close();
		}

		if ( isset($this->headers['LOCATION']) ) {
			$this->numRedirects++;
			if ( $this->numRedirects > $this->maxRedirects ) {
				$this->deffered->reject( new \Exception('ERR_TOO_MANY_REDIRECTS'));
				return;
			}
			$this->request('REDIRECT', $this->rel2abs( $this->headers['LOCATION'], $this->url ) );
			return;
		}
		if (isset($this->headers['STATUS-CODE']) && $this->headers['STATUS-CODE'] >= 400 ) {
			$this->deffered->reject( new \Exception( $this->headers['STATUS'], $this->headers['STATUS-CODE'] ));
			return;
		}
		$this->deffered->resolve( $this );
	}

	public function handleClose() {
		if ( isset($this->listeners['close'])) {
			$this->emit( 'close', [ $this ] );
		}
		$this->busy = false;
		$this->connection = null;
		if (isset($this->listeners['debug'])) {
			$this->emit('debug', [get_class($this).' closed']);
		}
	}

	public function handleError( \Exception $ex ) {
		$this->deffered->reject( $ex );
		$this->busy = false;
	}

	public function close() {
		$this->connection->close();
	}

	public function rel2abs( $relativeUrl, $baseUrl ) {

		// Skip converting if the relative url like http://... or android-app://... etc.
		if ( preg_match( '/[a-z0-9-]{1,}(:\/\/)/i', $relativeUrl ) ) {
			return $relativeUrl;
		}
		// Treat path as invalid if it is like javascript:... etc.
		if ( preg_match( '/^[a-zA-Z]{0,}:[^\/]{0,1}/i', $relativeUrl ) ) {
			return null;
		}

		$baseParts = $this->parseURL( $baseUrl );

		$baseHostUrl = $baseParts['scheme'].'://'.$baseParts['host'].':'.$baseParts['port'];
		$basePathUrl = $baseHostUrl.$baseParts['path'];

		// Convert //www.google.com to http://www.google.com
		if ( 0 === strpos( $relativeUrl, '//' ) ) {
			return $baseParts['scheme'].':' . $relativeUrl;
		}
		// If the path is a fragment or query string,
		// it will be appended to the base url
		if ( 0 === strpos( $relativeUrl, '#' ) || 0 === strpos( $relativeUrl, '?' ) ) {
			return $basePathUrl . $relativeUrl;
		}
		// Treat paths with doc root, i.e, /about
		if ( 0 === strpos( $relativeUrl, '/' ) ) {
			return $baseHostUrl . $relativeUrl;
		}
		// For paths like ./foo, it will be appended to the furthest directory
		if ( 0 === strpos( $relativeUrl, './' ) ) {
			return $this->uptoLastDir( $basePathUrl ) . substr( $relativeUrl, 2 );
		}
		// Convert paths like ../foo or ../../bar
		if ( 0 === strpos( $relativeUrl, '../' ) ) {
			$rel  = $relativeUrl;
			$base = $this->uptoLastDir( $basePathUrl );
			while ( 0 === strpos( $rel, '../' ) ) {
				$base = preg_replace( '/\/([^\/]+\/)$/', '/', $base );
				$rel  = substr( $rel, 3 );
			}

			return $base . $rel;
		}

		// else
		return $this->uptoLastDir( $basePathUrl ) . $relativeUrl;
	}
	// Get the path with last directory
	// http://example.com/some/fake/path/page.html => http://example.com/some/fake/path/
	private function uptoLastDir( $url ) {
		$url = preg_replace( '/\/([^\/]+\.[^\/]+)$/', '', $url );

		return rtrim( $url, '/' ) . '/';
	}
	private function parseURL( $url ) {
		$p = parse_url( $url );

		if ( $p === false ) {
			$p = [];
		}

		if (empty($p['scheme'])) {
			$p['scheme'] = 'http';
		}
		if (empty($p['host'])) {
			$p['host'] = '';
		}
		if ( empty($p['path'])) {
			$p['path'] = '/';
		}
		if ( empty($p['port'])) {
			$p['port'] = $p['scheme'] === 'https' ? '443' : '80';
		}

		return $p;
	}
}
