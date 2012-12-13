<?php

/**
 * BaseDevice - this is the base class that all other device extend
 */

namespace NinjaBlock\Device;

use Evenement\EventEmitter;

class BaseDevice extends EventEmitter
{
	protected $state;

	function __construct()
	{
		$this->V = 0;
		$this->D = 0;
		$this->G = "0";

		$this->state = 0;
	}

	function write( $data )
	{
		var_dump( $data );
		$this->emit( 'data', array( $data ) );
	}

	function getState()
	{
		return $state;
	}

	function emitState()
	{
		$this->emit( 'data', array( $this->getState() ) );
	}

}

?>