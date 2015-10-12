<?php

namespace NinjaBlock;

use React\Stream\Stream;

class MQTTClient extends \Evenement\EventEmitter
{

	private $loop;
	private $socket;
	private $stream;
	private $timer;
	private $timeouts = 0;

	private $msgid = 1;			/* counter for message id */
	public $keepalive = 10;		/* default keepalive timmer */
	public $lastping;		/* host unix time, used to detect disconects */
	public $topics = array(); 	/* used to store currently subscribed topics */
	public $debug = false;		/* should output debug messages */


	public $address;			/* broker address */
	public $port;				/* broker port */
	public $clientid;			/* client id sent to brocker */
	public $will;				/* stores the will of the client */
	private $username;			/* stores username */
	private $password;			/* stores password */

	private $states = [
		0 => "NOT CONNECTED",
		1 => "CONNECTING",
		2 => "CONNECTED"
	];

	private $current_state = 0;

	function __construct( $address, $port, $clientid, $loop)
	{
		$this->address = $address;
		$this->port = $port;
		$this->clientid = $clientid;		
		$this->loop = $loop;

		$that = $this;
		$this->on( 'connect', function() use ( $that ) {
			$that->timer = $that->loop->addTimer( $that->keepalive, array( $that, 'onTimeOut' ) );
		});
	}

	function connect($clean = true, $will = NULL, $username = NULL, $password = NULL)
	{
		if($will) $this->will = $will;
		if($username) $this->username = $username;
		if($password) $this->password = $password;

		$address = gethostbyname($this->address);	
		$this->socket = fsockopen($address, $this->port, $errno, $errstr, 60);

		if (!$this->socket ) {
		    error_log("fsockopen() $errno, $errstr \n");
			return false;
		}

		stream_set_timeout($this->socket, 5);
		stream_set_blocking($this->socket, 0);

		$this->stream = new Stream($this->socket, $this->loop);

		$this->stream->on( 'data', array( $this, 'handleData' ) );


		$i = 0;
		$buffer = "";

		$buffer .= chr(0x00); $i++;
		$buffer .= chr(0x06); $i++;
		$buffer .= chr(0x4d); $i++;
		$buffer .= chr(0x51); $i++;
		$buffer .= chr(0x49); $i++;
		$buffer .= chr(0x73); $i++;
		$buffer .= chr(0x64); $i++;
		$buffer .= chr(0x70); $i++;
		$buffer .= chr(0x03); $i++;

		//No Will
		$var = 0;
		if($clean) $var+=2;

		//Add will info to header
		if($this->will != NULL){
			$var += 4; // Set will flag
			$var += ($this->will['qos'] << 3); //Set will qos
			if($this->will['retain'])	$var += 32; //Set will retain
		}

		if($this->username != NULL) $var += 128;	//Add username to header
		if($this->password != NULL) $var += 64;	//Add password to header

		$buffer .= chr($var); $i++;

		//Keep alive
		$buffer .= chr($this->keepalive >> 8); $i++;
		$buffer .= chr($this->keepalive & 0xff); $i++;

		$buffer .= $this->strwritestring($this->clientid,$i);

		//Adding will to payload
		if($this->will != NULL){
			$buffer .= $this->strwritestring($this->will['topic'],$i);  
			$buffer .= $this->strwritestring($this->will['content'],$i);
		}

		if($this->username) $buffer .= $this->strwritestring($this->username,$i);
		if($this->password) $buffer .= $this->strwritestring($this->password,$i);

		$head = "  ";
		$head{0} = chr(0x10);
		$head{1} = chr($i);

		$this->current_state = 1; // CONNECTING
		$this->lastping = time();

		$this->stream->write( $head );
		$this->stream->write( $buffer );

		$this->stream->on( 'end', array( $this, 'reconnect' ) );

	}

	function reconnect()
	{
		$this->log( "notice", "MQTT Client Reconnecting" );
		$this->stream->end();
		$this->connect( false );

		// Re-subscribe
		$topics = $this->topics;
		$this->topics = array();
		$this->subscribe( $topics );
	}

	function subscribe( $topics, $qos = 0 )
	{
		$i = 0;
		$buffer = "";
		$id = $this->msgid;
		$buffer .= chr($id >> 8);  $i++;
		$buffer .= chr($id % 256);  $i++;

		foreach($topics as $key => $topic){
			$buffer .= $this->strwritestring($key,$i);
			$buffer .= chr($topic["qos"]);  $i++;
			$this->topics[$key] = $topic; 
		}

		$cmd = 0x80;
		//$qos
		$cmd +=	($qos << 1);


		$head = chr($cmd);
		$head .= chr($i);

		$this->stream->write( $head . $buffer );
	}

	/* publish: publishes $content on a $topic */
	function publish($topic, $content, $qos = 0, $retain = 0){

		$i = 0;
		$buffer = "";

		$buffer .= $this->strwritestring($topic,$i);

		//$buffer .= $this->strwritestring($content,$i);

		if($qos){
			$id = $this->msgid++;
			$buffer .= chr($id >> 8);  $i++;
		 	$buffer .= chr($id % 256);  $i++;
		}

		$buffer .= $content;
		$i+=strlen($content);

		$head = " ";
		$cmd = 0x30;
		if($qos) $cmd += $qos << 1;
		if($retain) $cmd += 1;

		$head{0} = chr($cmd);		
		$head .= $this->setmsglength($i);

		$this->stream->write( $head . $buffer );
	}


	function handleData( $data )
	{

		//$this->printstr( $data );

		$this->resetTimer();

		if ( $this->current_state == 1 )
		{
			// Connecting
			if ( ord($data{0})>>4 == 2 && $data{3} == chr(0) )
			{
				$this->emit( 'connect', array() );
				$this->current_state = 2;
			}
			else
			{
				ob_start();
				$this->printstr( $data );

				$this->emit( 'error', array( "Connection Failed" . PHP_EOL . ob_get_clean() ) );
			}
		}
		if ( $this->current_state == 2 )
		{
			// Connected

			switch ( ord($data{0})>>4 )
			{
				case 9:
					// SUBACK
					$this->emit( 'SUBACK' );
					$len = ord( $data{1} );
					if ( strlen( $data ) > ( 2 + $len ) )
					{
						$this->handleData( substr( $data, ( 2 + $len ) ) );
					}
					break;
				case 3:
					// PUBLISH (recv)
					$len = ord( $data{1} );
					$tlen = (ord($data{2})<<8) + ord($data{3});
					$topic = substr($data,4,$tlen);
					$msg = substr($data,($tlen+4), $len - $tlen - 2 );
					$this->emit( 'message', array( $topic, $msg ) );
					if ( strlen( $data ) > ( 2 + $len ) )
					{
						$this->handleData( substr( $data, ( 2 + $len ) ) );
					}

					break;
				case 13:
					// PING ACK
					$this->emit( 'pong', array() );
					break;
				case 2:
					$this->emit( 'CONNACK' );
					break;
				default:
					$this->printstr( $data );
					break;
			}
		}
		else
		{
			throw new Exception("Data in unknown state" );
		}

		if ( ( time() - $this->lastping ) >= $this->keepalive )
		{
			$this->ping();
		}
	}

	function ping(){
		$head = " ";
		$head = chr(0xc0);		
		$head .= chr(0x00);
		$this->stream->write( $head );
		$this->lastping = time();
		$this->emit( 'ping', array() );
	}

	function resetTimer()
	{
		if ( ! isset( $this->timer ) ) { return; }
		$newTimer = $this->loop->addTimer( $this->timer->getInterval(), array( $this, 'onTimeOut' ) );
		$this->timer->cancel();
		$this->timer = $newTimer;
		$this->timeouts = 0;
	}

	function onTimeout()
	{
		$this->emit( "timeout", array() );
		if ( $this->timeouts > 0 )
		{
			$this->reconnect();
			$this->current_state = 0;
		}
		else
		{
			$this->ping();
			$this->timeouts++;			
		}
	}

	/* setmsglength: */
	function setmsglength($len){
		$string = "";
		do{
		  $digit = $len % 128;
		  $len = $len >> 7;
		  // if there are more digits to encode, set the top bit of this digit
		  if ( $len > 0 )
		    $digit = ($digit | 0x80);
		  $string .= chr($digit);
		}while ( $len > 0 );
		return $string;
	}

	/* strwritestring: writes a string to a buffer */
	function strwritestring($str, &$i){
		$ret = " ";
		$len = strlen($str);
		$msb = $len >> 8;
		$lsb = $len % 256;
		$ret = chr($msb);
		$ret .= chr($lsb);
		$ret .= $str;
		$i += ($len+2);
		return $ret;
	}

	function printstr($string){
		$strlen = strlen($string);
			for($j=0;$j<$strlen;$j++){
				$num = ord($string{$j});
				if($num > 31) 
					$chr = $string{$j}; else $chr = " ";
				printf("%4d: %08b : 0x%02x : %s \n",$j,$num,$num,$chr);
			}
	}

	function isConnected()
	{
		return 2 == $this->current_state;
	}

	public function log( $level, $message ) {
		$this->emit( 'log', array( $level, $message ) );
	}

}