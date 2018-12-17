# react-http-client
ReactPHP async HTTP client, minimal dependencies:
https://reactphp.org/

## Basic Usage
```php
$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->get( 'https://tools.ietf.org/rfc/rfc2068.txt' )->then( function ( \Shuchkin\ReactHTTP\Client $client ) {

	echo $client->content;

}, function ( \Exception $ex ) {

	echo 'HTTP error '.$ex->getCode().' '.$ex->getMessage();

} );
```

## Post
```php
$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->post( 'https://reqres.in/api/users', '{"name": "morpheus","job": "leader"}' )->then( function ( \Shuchkin\ReactHTTP\Client $client ) {

	echo $client->content;

}, function ( \Exception $ex ) {

	echo 'HTTP error '.$ex->getCode().' '.$ex->getMessage();

} );
 // {"name":"morpheus","job":"leader","id":"554","createdAt":"2018-12-17T10:31:29.469Z"}
```

## Chunked encoding
```php
$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );
$http->get( 'https://jigsaw.w3.org/HTTP/ChunkedScript' )->then( function () {

	echo PHP_EOL . 'Mission complete';

}, function ( \Exception $ex ) {

	echo 'ERROR '.$ex->getCode().' '.$ex->getMessage();

} );

$http->on('chunk', function( $chunk ) {
	echo PHP_EOL.'-- CHUNK='.$chunk;
});

$loop->run();
```
## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require shuchkin/react-http-client
```