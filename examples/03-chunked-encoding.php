<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );
$http->get( 'https://jigsaw.w3.org/HTTP/ChunkedScript' )->then( function ( \Shuchkin\ReactHTTP\Client $client ) {
	echo PHP_EOL.'HEADERS------------------------';
	/** @noinspection ForgottenDebugOutputInspection */
	print_r( $client->headers );

}, function ( \Exception $ex ) {

	echo 'ERROR '.$ex->getCode().' '.$ex->getMessage();

} );

$http->on('chunk', function( $chunk, \Shuchkin\ReactHTTP\Client $client ) {
	echo PHP_EOL.'-- CHUNK='.$chunk;
	//$client->
});

//$http->on('debug', function( $s ) { echo trim($s).PHP_EOL; } );

$loop->run();