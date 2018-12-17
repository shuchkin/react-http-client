<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$http = new \Shuchkin\ReactHTTP\Client( $loop );

$http->get( 'https://tools.ietf.org/html/rfc2616' )->then( function ( $content ) {

	echo $content;

}, function ( \Exception $ex ) {

	echo 'HTTP error '.$ex->getCode().' '.$ex->getMessage();

} );

//$http->on('debug', function( $s ) { echo trim($s).PHP_EOL; } );

$loop->run();