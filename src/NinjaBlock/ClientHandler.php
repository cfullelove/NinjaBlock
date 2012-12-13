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
		
		$ds = $command->DEVICE;
		
		for ( $i = 0; $i<count($ds); $i++ )
		{
			$guid = $ds[$i]->GUID;
			$device = $this->client->getDevice( $guid );

			if ( $device !== false  )
			{
				$device->write( $ds[$i]->DA );
			}
			else
			{
				printf( "Tried to execute a command on a device that doesn't exist!\n" );
				call_user_func( $fn, true );
				return;
			}
		}

		call_user_func( $fn, null );
	}

	function update( $toUpdate )
	{
		echo "update!";
	}
}

?>