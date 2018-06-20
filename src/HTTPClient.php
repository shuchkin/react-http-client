<?php

namespace SMSPilot;

class HTTPClient extends \Evenement\EventEmitter {
	public $headers;
	public $body;
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

//	private $keepAlive;
	public function __construct( \React\EventLoop\LoopInterface $loop, $connector = null ) {
		$this->loop         = $loop;
		$this->connector    = $connector === null ? new \React\Socket\Connector( $this->loop ) : $connector;
		$this->maxRedirects = 10;
		$this->numRedirects = 0;
	}

	public function get( $url, array $headers = [] ) {
		return $this->request( 'GET', $url, $headers );
	}

	public function request( $method, $url, $data = null, array $headers = [] ) {
		if ( $this->busy ) {
			$new_client = new self( $this->loop );
			$new_client->cookie = $this->cookie;
			$new_client->url = $this->url;

			return $new_client->request( $method, $url, $data, $headers );
		}
		if ( $method === 'REDIRECT' ) {
			$method = 'GET';
		} else {
			$this->deffered = new \React\Promise\Deferred();
		}
		$this->buffer        = '';
		$this->headers       = [];
		$this->body          = null;
		$this->contentLength = false;
		$this->chunked       = false;
		$this->busy          = true;

		$parts = $this->parseURL( $url );

		if ( !$parts['host'] ) {
			return new \React\Promise\RejectedPromise( new \Exception( 'Bad URL ' . $url ) );
		}
		if ( $this->url ) {

			if ( $this->connection ) {
				$old = $this->parseURL( $this->url );

				if ( $parts['host'] !== $old['host'] || $parts['port'] !== $old['port'] ) {

					$this->connection->close();
					$this->connection = null;
					$this->cookie     = [];
				}
			}
		}

		$this->url = $url;

		$headers['Host'] = $parts['host'];

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
		$request = strtoupper( $method ) . ' ' . $parts['path'] . ( empty( $parts['query'] ) ? '?' . $parts['query'] : '' ) . " HTTP/1.1\r\n";

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

		if ( $this->connection && $this->connection->isReadable() ) {
			$this->connection->write( $request );
		} else {
			/** @noinspection NullPointerExceptionInspection */
			$this->connector->connect( $connect_uri )->then( function ( \React\Socket\ConnectionInterface $connection ) use ( $request ) {
				if ( isset($this->listeners['debug'])) {
					$this->emit('debug',['Connected to '.$connection->getRemoteAddress()] );
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

		return $this->deffered->promise();
	}

	public function post( $url, $data, array $headers = [] ) {
		return $this->request( 'POST', $url, $data, $headers );
	}

	public function handleData( $data ) {

		if ( isset($this->listeners['debug'])) {
			$this->emit('debug', [ $data ] );
		}
		$this->buffer .= $data;

		// read headers
		if ( $this->body === null ) {
			while ( false !== $pos = strpos( $this->buffer, "\r\n" ) ) {
				$line         = substr( $this->buffer, 0, $pos );
				$this->buffer = substr( $this->buffer, $pos + 2 );

				if ( $p = strpos( $line, ':' ) ) {
					$header                   = substr( $line, 0, $p );
					$header                   = ucwords( strtolower( $header ), '-' ); // CONTENT-LeNgTH -> Content-Length
					$this->headers[ $header ] = trim( substr( $line, $p + 1 ) );
				} else if ( preg_match( '/^HTTP\/^\S*\s(.*?)\s/', $line, $m ) ) {
					$this->headers['Status-Code'] = $m[1];
				} else { // body
					$this->contentLength = isset( $this->headers['Content-Length'] ) ? $this->headers['Content-Length'] : false;
					$this->chunked       = isset( $this->headers['Transfer-Encoding'] ) && ( $this->headers['Transfer-Encoding'] === 'chunked' );
					if ( isset($this->headers['Set-Cookie'])
						&& preg_match_all( '/([^=]+)=([^;]+);/', $this->headers['Set-Cookie'], $m ) ) {

						foreach ( $m[0] as $k => $v ) {
							$this->cookie[ $m[1][ $k ] ] = $m[2][ $k ];
						}
					}
					$this->body          = '';


				}

			}
		} else if ( $this->contentLength && \mb_strlen( $this->buffer, 'latin1' ) >= $this->contentLength ) {
			$this->handleEnd();
		}

		/*			if
					// read content
					if ( $content_length !== false) {

						$_size = 4096;
						do {
							$_data = fread($fp, $_size );
							$content .= $_data;
							$_size = min($content_length-strlen($content), 4096);
						} while( $_size > 0 );

					} else if ($chunked) {

						while ( $chunk_length = hexdec(trim(fgets($fp))) ) {

							$chunk = '';
							$read_length = 0;

							while ( $read_length < $chunk_length ) {

								$chunk .= fread($fp, $chunk_length - $read_length);
								$read_length = strlen($chunk);

							}
							$content .= $chunk;

							fgets($fp);

						}
					} else {
						while(!feof($fp)) {
							$content .= fread( $fp, 4096 );
						}
					}
					fclose($fp);

		//		echo $content;
		//		file_put_contents('cache/http_request.log',print_r(get_defined_vars(), true).$eol.$eol.$eol, FILE_APPEND);

					return $content;*/
	}

	public function handleEnd() {
		$this->busy = false;

		if ( isset( $this->headers['Connection'] ) && strtoupper( $this->headers['Connection']) === 'CLOSE' ) {
			$this->connection->close();
			$this->connection = null;
		}

		if ( isset($this->headers['Location']) ) {
			$this->request('REDIRECT', $this->rel2abs( $this->headers['Location'], $this->url ) );
			return;
		}
		$this->body   = $this->buffer;
		$this->buffer = '';
		$this->deffered->resolve( $this );
	}

	public function handleClose() {
		$this->busy = false;
		$this->connection = null;
	}

	public function handleError( \Exception $ex ) {
		$this->deffered->reject( $ex );
		$this->busy = false;
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
