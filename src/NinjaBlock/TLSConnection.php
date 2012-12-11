<?php

namespace NinjaBlock;

use React\Socket\ConnectionInterface;
use React\Stream\Stream;

class TLSConnection extends Stream implements ConnectionInterface
{
	public function getRemoteAddress()
    {
        return $this->parseAddress(stream_socket_get_name($this->stream, true));
    }
}

?>