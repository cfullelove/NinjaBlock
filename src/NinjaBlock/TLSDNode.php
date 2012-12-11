<?php

namespace NinjaBlock;

use DNode\DNode;
use NinjaBlock\TLSConnection;
use React\EventLoop\LoopInterface;

class TLSDNode extends DNode
{

	private $loop;
    private $protocol;

	public function __construct( LoopInterface $loop, $wrapper = null )
	{
		$this->loop = $loop;

        $wrapper = $wrapper ?: new \StdClass();
        $this->protocol = new \DNode\Protocol($wrapper);

        parent::__construct( $loop, $wrapper );
	}

	public function connect()
    {
        $params = $this->protocol->parseArgs(func_get_args());
        if (!isset($params['host'])) {
            $params['host'] = '127.0.0.1';
        }

        if (!isset($params['port'])) {
            throw new \Exception("For now we only support TCP connections to a defined port");
        }

        $client = stream_socket_client("tls://{$params['host']}:{$params['port']}");
        
        if (!$client) {
            throw new \RuntimeException("No connection to DNode server in tcp://{$params['host']}:{$params['port']}");
        }

        $conn = new TLSConnection($client, $this->loop);
        $this->handleConnection($conn, $params);
	}
}