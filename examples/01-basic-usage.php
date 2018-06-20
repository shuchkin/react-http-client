<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$http = new \SMSPilot\HTTPClient( $loop );
$http->get( 'http://ya.ru/notfound' )->then( function ( \SMSPilot\HTTPClient $client ) {

	echo print_r( $client->body, true );
	echo print_r( $client->headers, true );

}, function ( \Exception $ex ) {

	echo $ex->getCode().' '.$ex->getMessage();

} );

$http->on('debug', function( $s ) { echo trim($s).PHP_EOL; } );

$loop->run();