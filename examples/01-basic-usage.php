<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->get( 'https://jigsaw.w3.org/HTTP/TE/foo.txt' )->then( function ( \Shuchkin\ReactHTTP\Client $client ) {

	echo 'Content='.$client->body;

}, function ( \Exception $ex ) {

	echo 'HTTP error '.$ex->getCode().' '.$ex->getMessage();

} );

$loop->run();