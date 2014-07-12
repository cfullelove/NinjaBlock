<?php

/**
 * ClientHandler class - this class handles the function calls made by NinjaCloud
 */

namespace NinjaBlock;

class ClientHandler
{
	private $client;

	function __construct( $client )
	{
		$this->client = $client;
	}

	function revokeCredentials()
	{
		printf( "Revoking token!" );
		$this->client->setToken( false );
	}

	function execute( $command, $fn )
	{
		printf( "Execute: %s\n", $command );

		$command = json_decode( $command );
		$this->client->emit( 'command', array( $command ) );

		unset( $command->DEVICE[0]->GUID );
		unset( $command->TIMESTAMP );
		
		$this->client->emit( 'write', array( $command ) );

		call_user_func( $fn, null );
	}

	function update( $toUpdate )
	{
		echo "update!";
	}
}

?>
