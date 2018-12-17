<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->get( 'https://raw.githubusercontent.com/shuchkin/react-http-client/master/README.md' )->then( function ( \Shuchkin\ReactHTTP\Client $client ) {

	echo $client->content;

}, function ( \Exception $ex ) {

	echo 'HTTP error '.$ex->getCode().' '.$ex->getMessage();

} );

//$http->on('debug', function( $s ) { echo trim($s).PHP_EOL; } );

$loop->run();