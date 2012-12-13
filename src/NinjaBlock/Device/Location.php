<?php

namespace NinjaBlock\Device;

use NinjaBlock\Device\BaseDevice;

class Location extends BaseDevice
{
	function __construct()
	{
		$this->V = 1;
	    $this->D = 2;
	    $this->G = "0";

		$this->loc = array( -27.549677, 152.987366 );
	}


	function jiggle()
	{
		$this->loc[0] += (rand( 0, 100 ) - 50)/10000;
		$this->loc[1] += (rand( 0, 100 ) - 50)/10000;

		$this->state = $this->getState();
	}

	function getState()
	{
		return sprintf( "%s,%s", $this->loc[0], $this->loc[1] );
	}
}

?>