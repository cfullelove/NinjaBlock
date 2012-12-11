<?php

namespace NinjaBlock;

use NinjaBlock\Device;

class NetworkDevice extends Device
{
	function __construct()
	{
		$this->V = 0;
	    $this->D = 1005;
	    $this->G = "0";
	}

	function write( $data )
	{
		$da = json_decode( $data );
		
		switch( $da->method )
		{
			case "SCAN":
				$this->SCAN( $da->id );
				break;
			default:
				break;
		}
		return true;
	}

	function SCAN( $id )
	{
		$result = (object) array (
			"result" => array( 
				"ethernet" => array(
					array( 
						"address" => 'unknown',
						'family' => 'IPv4',
						'internal' =>false ),
					array(
						"address" => 'unknown',
						'family' => 'IPv6',
						'internal' =>false ))),
			"error" => null,
			"id" => $id
			);
		$this->emit( "data", array( json_encode( $result ) ) );
	}
}

?>