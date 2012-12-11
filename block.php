<?php

require( "vendor/autoload.php" );

$loop = \React\EventLoop\Factory::create();

$client = new NinjaBlock\Client( '1012CF013284', "7d1d2b25-d476-4535-8f76-9b54c3aae32f" );

$client->registerDevice( new NinjaBlock\NetworkDevice() );

$dnode = new DNode\DNode($loop, new NinjaBlock\ClientHandler( $client ) );

//$dnode->connect("vps2.rednesstech.com", 4444, function($remote, $connection) use ($loop, $client) {
$dnode->connect("vps2.rednesstech.com", 4444, function($remote, $connection) use ($loop, $client) {
	
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
				throw Exception( "Failed to handshake: %s", var_export( $err, true ) );
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
		$remote->activate( $params, function( $err, $auth ) use ($client, $remote, $connection) {
			
			if ( $err !== null )
			{
				throw Exception( "Failed to activate: %s", var_export( $err, true ) );
			}

			$client->setToken( $auth->token );
			
			printf( "Received new token: %s\n", $client->getToken() );
			$remote->confirmActivation( $client->params, function( $err ) use ($connection) {
				if ( $err !== null )
				{
					throw Exception( "Failed to confirm activation: %s", var_export( $err, true ) );
				}

				printf( "Activation confirmed" );
				$connection->emit( 'handshake', array() );
			});
		});
	
	}
});

$loop->run();

?>