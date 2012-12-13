<?php

/**
 * Temperature - fake temperature sensor that randomly changes temperature
 */

namespace NinjaBlock\Device;

use NinjaBlock\Device\BaseDevice;

class Temperature extends BaseDevice
{
	private $temperature;

	function __construct()
	{
		$this->V = 0;
	    $this->D = 9;
	    $this->G = "0";

	    $this->temperature = 25;
	}

	function write( $data )
	{
		//$this->color = $data;
		var_dump( $data );
		$this->emit( 'data', array( $data ) );
	}

	function getState()
	{
		return $this->temperature;
	}


	function jiggle()
	{
		$this->temperature = $this->temperature + ( rand( 0, 20 ) - 10 )/10;
	}
	
}

?>