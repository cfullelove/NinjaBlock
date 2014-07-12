<?php

require( "vendor/autoload.php" );


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

$client->on( 'write', function( $command ) use ($writeCmd) {
	printf( "recvData: %s\n", json_encode( $command ) );
	if ( $writeCmd !== false )
	{
		$cmd = sprintf( "echo '%s' | %s", json_encode( $command ), $writeCmd );
		//echo $cmd . PHP_EOL;
		echo shell_exec( $cmd );
	}
});

$block = new NinjaBlock\Block( $loop, $client );

$readStream = new NinjaBlock\Stream( fopen( 'php://stdin', 'r' ), $loop );
$writeStream = new \React\Stream\Stream( fopen( 'php://stdout', 'w' ), $loop );

$block->setReadStream( $readStream );
$block->setWriteStream( $writeStream );

$block->connect();

$loop->addPeriodicTimer( 300, function() use ($block) {
	printf( "Exiting...\n");
	//$block->reconnect();
	$block->disconnect();
	exit();
});

$loop->run();

?>
