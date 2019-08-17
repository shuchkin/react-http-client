# react-http-client 0.2
[<img src="https://img.shields.io/endpoint.svg?url=https%3A%2F%2Fshieldsio-patreon.herokuapp.com%2Fshuchkin" />](https://www.patreon.com/shuchkin)

ReactPHP async HTTP client, minimal dependencies:
https://reactphp.org/

## Basic Usage
```php
$loop = \React\EventLoop\Factory::create();
$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->get( 'http://api.ipify.org' )->then(
	function( $content ) {
		echo $content; // 123.12.12.1
	}
);

$loop->run();
```

## Post
```php
$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->post( 'https://reqres.in/api/users', '{"name": "morpheus","job": "leader"}' )->then(
	function ( $content ) {
		echo $content;
	},
	function ( \Exception $ex ) {
		echo 'HTTP error '.$ex->getCode().' '.$ex->getMessage();
	}
);

$loop->run();

// {"name":"morpheus","job":"leader","id":"554","createdAt":"2018-12-17T10:31:29.469Z"}
```

## Send headers
```php
$loop = \React\EventLoop\Factory::create();
$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->get('https://jigsaw.w3.org/HTTP/TE/foo.txt',['User-Agent' => 'ReactPHP Awesome'] )->then(
	function ( $content ) {
		echo $content;
	},
	function ( \Exception $ex ) {
		echo 'HTTP error '.$ex->getCode().' '.$ex->getMessage();
	}
);
$loop->run();																					
```

## Read chunks
```php
$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );
$http->get( 'https://jigsaw.w3.org/HTTP/ChunkedScript' )->then(
	function () {
		echo PHP_EOL . 'Mission complete';
	},
	function ( \Exception $ex ) {
		echo 'ERROR '.$ex->getCode().' '.$ex->getMessage();
	}
);

$http->on('chunk', function( $chunk ) {
	echo PHP_EOL.'-- CHUNK='.$chunk;
});

$loop->run();
```

## Get headers & debug
```php
$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->request('GET','https://reqres.in/api/users')->then(
	function( \Shuchkin\ReactHTTP\Client $client ) {
		// dump response headers
		print_r( $client->headers );
		// dump content
		echo PHP_EOL . $client->content;
	},
	function ( \Exception $ex ) {
		echo 'ERROR '.$ex->getCode().' '.$ex->getMessage();
	}
);
// enable debug mode
$http->on('debug', function( $s ) { echo trim($s).PHP_EOL; } );
$loop->run();
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require shuchkin/react-http-client
```
