<?php

namespace NinjaBlock;

use React\Socket\ConnectionInterface;

class TLSConnection extends \React\Stream\Stream implements ConnectionInterface
{
    public function getRemoteAddress()
    {
        return $this->parseAddress(stream_socket_get_name($this->stream, true));
    }
}

?>
