<?php

namespace Match2Pay;

class Logger {

	/**
	 * @var Logger
	 */
	private static $instance;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Match2Pay Logger to log all the debugs
	 */
	public function write_log( $log, $log_enabled = true ) {

		if ( $log_enabled ) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'match2pay-plugin' );
			$logger->info( $log, $context );
		}
		error_log( print_r( $log, true ) );
	}
}
