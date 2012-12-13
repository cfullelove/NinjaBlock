<?php

require( "vendor/autoload.php" );

/**
	Set you block ID here
**/
$block_id = '1012CF013284';

// Create the event loop;
$loop = \React\EventLoop\Factory::create();

use NinjaBlock\Device as NinjaDevice;

// Create the NinaBlock Client
$client = new NinjaBlock\Client( $block_id );

// Register a Network Device
$client->registerDevice( new NinjaDevice\Network() );
$client->registerDevice( new NinjaDevice\RGBLED() );

$temp = new NinjaDevice\Temperature();
$loop->addPeriodicTimer( 5, function() use ($temp) {
	$temp->jiggle();
	$temp->emit( 'data', array( $temp->getState() ) );
});
$client->registerDevice( $temp );

$gps = new NinjaDevice\Location();
$loop->addPeriodicTimer( 5, function() use ($gps) {
	$gps->jiggle();
	$gps->emitState();
});
$client->registerDevice( $gps );

// Build the DNode and associate with the NinjaBlock ClientHandler
$dnode = new NinjaBlock\TLSDNode( $loop, new NinjaBlock\ClientHandler( $client ) );

// Connect to ninacloud
$dnode->connect("zendo.ninja.is", 443, function($remote, $connection) use ($loop, $client) {
	
	printf( "Connected to Ninja Cloud\n" );

	$connection->on( "up", function( $remote ) use ( $connection, $loop, $client ) {

		// give our client a remote
		$client->setRemote( $remote );

		// setup the heartbeat
		$loop->addPeriodicTimer( 2, function() use ($remote, $client ) {
			printf( "Sending Heartbeat....\n" );
			call_user_func( $remote->heartbeat, $client->get_heartbeat() );
		});
	});

	$connection->on( 'handshake', function () use ($remote, $connection, $client) {
		$remote->handshake( $client->getParams(), $client->getToken(), function( $err, $res ) use ($connection) {
			if ( $err !== null )
			{
				throw new Exception( sprintf( "Failed to handshake: %s", var_export( $err, true ) ) );
			}
			$connection->emit( "up", array( $res ) );
		});
	});

	if ( $client->getToken() !== false )
	{
		printf( "We have a token, time to handshake\n");
		$connection->emit( 'handshake', array() );
	}
	else
	{
		printf( "We need a token, waiting for activation, use id: %s\n", $client->getParams()->id );
		$remote->activate( $client->getParams(), function( $err, $auth ) use ($client, $remote, $connection) {
			
			if ( $err !== null )
				throw new Exception( sprintf( "Failed to activate: %s", var_export( $err, true ) ) );

			$client->setToken( $auth->token );

			$params = $client->getParams();
			$params->token = $auth->token;
			
			printf( "Received new token: %s\n", $auth->token );
			$remote->confirmActivation( $params, function( $err ) use ($connection) {

				if ( $err !== null )
					throw new Exception( sprintf( "Failed to confirm activation: %s", var_export( $err, true ) ) );

				printf( "Activation confirmed" );
				$connection->emit( 'handshake', array() );
			});
		});
	
	}
});

$loop->run();

?>