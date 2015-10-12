<?php


function addLogger( \Evenement\EventEmitter $class, \Monolog\Logger $logger ) {
	$class->on( 'log', function( $level, $message) use ($logger) {
		call_user_func( array( $logger, 'add' . ucfirst( $level ) ), $message );
	});
}

?>