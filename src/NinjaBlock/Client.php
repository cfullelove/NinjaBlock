<?php

namespace NinjaBlock;

use NinjaBlock\Device\BaseDevice;

class Client extends \Evenement\EventEmitter
{
	private $devices = array();
	private $remote, $params;
	private $token;
	private $token_file;

	function __construct( $id )
	{
		$this->params = (object) array(
			"client" => 'cfullelovePHPblock',
			"id" => $id,
			"version" => array(
				"node" => "0.8",
				"utilities" => "0.7",
				"system" => "0.5",
				"arduino" => array( 
					"model" => "V11",
					"version" => "0.44",
					)
				)
			);

		$this->token_file = dirname(__FILE__).'/../../ninja.token';

		if ( file_exists( $this->token_file ) )
			$this->token = file_get_contents( $this->token_file );
		else
			$this->token = null;

	}

	function getParams()
	{
		return $this->params;
	}

	function getToken()
	{
		if ( $this->token == null )
		{
			return false;
		}
		else
		{
			return $this->token;
		}
	}

	function setToken( $token )
	{
		if ( $token == false )
		{
			unlink( $this->token_file );
		}

		if ( $token != null && $token != "" )
		{
			$this->token = $token;
			return file_put_contents( $this->token_file, $token );
		}
	}

	function setRemote( $remote )
	{
		$this->remote = $remote;
		foreach( $this->devices as $device )
		{
			$device->emit( 'data', array( $device->getState() ) );
		}
	}

	function registerDevice( BaseDevice $device )
	{
		$that = $this;
		$device->guid = $this->buildDeviceGuid( $device );

		$device->on( 'data', function ( $data ) use ( $that, $device ) {
			$d = array(
				"G" => $device->G,
				"V" => $device->V,
				"D" => $device->D,
				"DA" => $data );
			$that->sendData( $d );
		});

		printf( "Registered: %s\n", $device->guid );
		$this->devices[ $device->guid ] = $device;
	}

	function getDevice( $guid )
	{
		if ( isset( $this->devices[$guid] ) )
		{
			return $this->devices[$guid];
		}
		else
		{
			return false;
		}
	}

	function buildDeviceGuid( $device )
	{
		return $this->params->id . '_' . $device->G . '_' . $device->V . '_' .$device->D;
	}

	function sendData( $data )
	{
		if ( isset( $this->remote->data ) )
		{
			$data['TIMESTAMP'] = time()*1000;
			$data = array( "DEVICE" => array($data) );
			call_user_func( $this->remote->data, $data );
			printf( "sendData: %s\n", json_encode( $data ) );
		}
		else
		{
			printf( "No remote!\n" );
		}
	}

	function get_heartbeat()
	{
		return array(
			"NODE_ID" => $this->params->id,
	        "TIMESTAMP" => time() * 1000,
	        "DEVICE" => array() );
	}

}

?>
