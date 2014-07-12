<?php

namespace NinjaBlock;

class Stream extends \React\Stream\Stream
{

	public function resume()
	{

		$this->loop->addReadStream($this->stream, array($this, 'handleData'));
	}

	public function handleData( $stream )
	{
		$data = fgets( $stream );
		$this->emit( 'data', array( trim( $data ) ), $this );
		

		if ( !is_resource( $stream ) || feof( $stream ) )
		{
			$this->end();
		}
	}
}
