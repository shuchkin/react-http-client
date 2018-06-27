<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$http = new \Pilot\HTTP\Client( $loop );
$http->get( 'https://jigsaw.w-3.org/HTTP/ChunkedScript' )->then( function ( \Pilot\HTTP\Client $client ) {
	echo "\r\nHEADERS------------------------\r\n";
	/** @noinspection ForgottenDebugOutputInspection */
	print_r( $client->headers );
	echo "\r\nBODY-------------------------\r\n".$client->body;
	echo "\r\n".strlen( $client->body );

}, function ( \Exception $ex ) {

	echo 'ERROR '.$ex->getCode().' '.$ex->getMessage();

} );

$http->on('chunk', function( $chunk ) {
	echo "\r\n-- CHUNK=".$chunk;
});

//$http->on('debug', function( $s ) { echo trim($s).PHP_EOL; } );

$loop->run();