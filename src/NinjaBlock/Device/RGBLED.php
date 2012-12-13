<?php

/**
 * RGBLED device - this represents the LED on the ninja block. Does what it is told at the moment
 */

namespace NinjaBlock\Device;

use NinjaBlock\Device\BaseDevice;

class RGBLED extends BaseDevice
{
	private $color;

	function __construct()
	{
		$this->V = 0;
	    $this->D = 1000;
	    $this->G = "0";

	    $this->color = "FFFFFF";
	}

	function write( $data )
	{
		$this->color = $data;
		$this->emit( 'data', array( $data ) );
	}

	function getState()
	{
		return $this->color;
	}
	

}

?>