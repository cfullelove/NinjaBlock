<?php

require( "vendor/autoload.php" );

// create a log channel
$log = new \Monolog\Logger('NinjaBlock');
$log->pushHandler(new Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::INFO));


if ( ! isset( $argv[1] ) )
	die( "You need to set a block id! (arg 1)");

$block_id = $argv[1];

if ( isset( $argv[2] ) )
{
	$writeCmd = $argv[2];
}
else
{
	$writeCmd = false;
}

// Create the event loop;
$loop = \React\EventLoop\Factory::create();

// Create the NinaBlock Client
$client = new NinjaBlock\Client( $block_id );

$block = new NinjaBlock\Block( $loop, $client );

$mqtt = new NinjaBlock\MQTTClient("dns.lan", 1883, "NinjaBlock", $loop);

addLogger( $client, $log );
addLogger( $block, $log );
addLogger( $mqtt, $log );

$readStream = new \React\Stream\ThroughStream();
$writeStream = new \React\Stream\ThroughStream();

$block->setReadStream( $readStream );
$block->setWriteStream( $writeStream );

$mqtt->on( 'connect', function() use ($mqtt, $readStream, $block_id) {
	$mqtt->log( 'info', "MQTT Connected" );
	$topic = sprintf( "RedNinja/%s/read", $block_id );
	$mqtt->log( 'info', "Subscribing: " . $topic );
	$mqtt->subscribe([
		$topic => ["qos" => 0 ]
	]);
});

$mqtt->on( 'message', function( $topic, $message) use ( $readStream ) {
	$readStream->write( $message );
});

$mqtt->on( 'timeout', function( ) { echo "TIMEOUT" .PHP_EOL; });

$client->on( 'write', function( $command ) use ($mqtt, $block_id) {
	$mqtt->log( "debug", "Publishing: " . json_encode( $command ) );
	$mqtt->publish( sprintf( "RedNinja/%s/write", $block_id ), json_encode( $command ) );
});

$block->on( 'connect', function() use ($mqtt) {
	if ( ! $mqtt->isConnected() )
		$mqtt->connect();
});

$block->connect();

$loop->run();

?>
