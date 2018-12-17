<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );
$http->get( 'https://jigsaw.w3.org/HTTP/TE/foo.txt' )->then( function ( \Shuchkin\ReactHTTP\Client $client ) {

	echo 'HEADERS------------------------'.PHP_EOL;

	/** @noinspection ForgottenDebugOutputInspection */
	print_r( $client->headers );

	echo PHP_EOL.'BODY-------------------------'.PHP_EOL.$client->content;

	echo PHP_EOL.'Content real length='.strlen( $client->content );

}, function ( \Exception $ex ) {

	echo 'HTTP error '.$ex->getCode().' '.$ex->getMessage();

} );

$loop->run();