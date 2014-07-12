<?php

namespace NinjaBlock;

class Block
{

	private $connectCallback;
	private $restarting = false;

	function __construct( \React\EventLoop\LoopInterface $loop, Client $client )
	{
		$this->loop = $loop;
		$this->client = $client;

		$that = $this;
	}

	function setReadStream( Stream $readStream )
	{
		$client = $this->client;
		$this->readStream = $readStream;
		$this->readStream->pause();
		$this->readStream->on( 'data', function( $data ) use ( $client ) {
			$data = json_decode( $data, true );
			if ( isset( $data['DEVICE'] ) )
			{
				$client->sendData( $data['DEVICE'][0] );
			}
			else
			{

			}
		});
	}

	function setWriteStream( \React\Stream\StreamInterface $stream )
	{
		$this->writeStream = $stream;
	}

	public function connect()
	{
		$that = $this;
		
		// Build the DNode and associate with the NinjaBlock ClientHandler
		$this->dnode = new TLSDNode( $this->loop, new ClientHandler( $this->client ) );
		try
		{
			$this->dnode->connect("zendo.ninja.is", 443, function( $remote, $connection ) use ($that) {
				call_user_func( array( $this, 'onConnect' ), $remote, $connection );
			});			
		}
		catch( \Exception $e )
		{
			exit();
		}
	}

	public function onConnect($remote, $connection)
	{
		$that = $this;
		$client = $this->client;

		printf( "Connected to Ninja Cloud\n" );

		// $connection->on( 'end', function() use ($that) {
		// 	if ( ! $that->restarting )
		// 	{
		// 		$that->reconnect();
		// 	}
		// 	else
		// 	{
		// 		return;
		// 	}
		// });

		if ( $client->getToken() !== false )
		{
			printf( "We have a token, time to handshake\n");

			$remote->handshake( $client->getParams(), $client->getToken(), function( $err, $res ) use ($that ) {
				if ( $err !== null )
				{
					throw new Exception( sprintf( "Failed to handshake: %s", var_export( $err, true ) ) );
				}
				$that->onBlockConnect( $res );
			});
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
	}

	public function onBlockConnect( $remote )
	{
		$that = $this;
		$client = $this->client;

		printf( "Client Connected\n");

		$client->setRemote( $remote );

		$this->heartbeat = $this->loop->addPeriodicTimer( 2, function() use ($remote, $client ) {
			printf( "Sending Heartbeat....\n" );
			call_user_func( $remote->heartbeat, $client->get_heartbeat() );
		});

		if ( isset( $this->readStream ) )
		{
			$this->readStream->resume();
		}
	}

	public function disconnect()
	{
		printf( "Disconnecting..." );
		$this->dnode->getConnection()->removeAllListeners();
		$this->restarting = true;
		$this->loop->cancelTimer( $this->heartbeat );
		$this->client->setRemote( null );
		$this->dnode->end();
		unset( $this->dnode );
		if ( isset( $this->readStream ) )
		{
			$this->readStream->pause();
		}
		printf( "done\n" );
	}

	public function reconnect()
	{
		$this->disconnect();

		$this->connect();
		$this->restarting = false;
	}
}