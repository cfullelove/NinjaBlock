<?php

namespace NinjaBlock;

class Block extends \Evenement\EventEmitter
{

	private $connectCallback;
	private $restarting = false;

	function __construct( \React\EventLoop\LoopInterface $loop, Client $client )
	{
		$this->loop = $loop;
		$this->client = $client;

		$that = $this;

	}

	function setReadStream( \React\Stream\StreamInterface $readStream )
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
				call_user_func( array( $that, 'onConnect' ), $remote, $connection );
			});			
		}
		catch( \Exception $e )
		{
			var_dump( $e );
			exit();
		}
	}

	public function onConnect($remote, $connection)
	{
		$that = $this;
		$client = $this->client;

		$this->emit( "initial-connect", array() );
		$this->log( "info", "Connected to Ninja Cloud" );

		$this->loop->addTimer( 600, array( $this, 'reconnect' ) );

		if ( $client->getToken() !== false )
		{
			$this->log( "info", "We have a token, time to handshake");

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
			$this->log( "notice", sprintf( "We need a token, waiting for activation, use id: %s\n", $client->getParams()->id ) );

			$remote->activate( $client->getParams(), function( $err, $auth ) use ($client, $remote, $connection) {
				
				if ( $err !== null )
					throw new Exception( sprintf( "Failed to activate: %s", var_export( $err, true ) ) );

				$client->setToken( $auth->token );

				$params = $client->getParams();
				$params->token = $auth->token;
				
				$this->log( "info", sprintf( "Received new token: %s\n", $auth->token ) );
				$remote->confirmActivation( $params, function( $err ) use ($connection) {

					if ( $err !== null )
						throw new Exception( sprintf( "Failed to confirm activation: %s", var_export( $err, true ) ) );

					$this->log( "info", "Activation confirmed" );
					$connection->emit( 'handshake', array() );
				});
			});
		
		}
	}

	public function onBlockConnect( $remote )
	{
		$that = $this;
		$client = $this->client;

		$this->log( "info", "NinjaBlock Client Connected");

		$client->setRemote( $remote );

		$this->heartbeat = $this->loop->addPeriodicTimer( 5, function() use ($remote, $client ) {
			call_user_func( $remote->heartbeat, $client->get_heartbeat() );
		});

		if ( isset( $this->readStream ) )
		{
			$this->readStream->resume();
		}

		$this->emit( 'connect', array() );
	}

	public function disconnect()
	{
		$this->log( "notice", "Disconnecting..." );
		$this->loop->cancelTimer( $this->heartbeat );
		$this->client->setRemote( null );
		$this->dnode->end();
		unset( $this->dnode );
		if ( isset( $this->readStream ) )
		{
			$this->readStream->pause();
		}
	}

	public function reconnect()
	{
		$this->disconnect();
		sleep( 2 );
		$this->loop->addTimer( 2, array( $this, 'connect' ) );
	}

	private function log( $level, $message ) {
		$this->emit( 'log', array( $level, $message ) );
	}
}