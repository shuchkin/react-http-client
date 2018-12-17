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

