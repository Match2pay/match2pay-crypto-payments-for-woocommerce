<?php

namespace Match2Pay;
use Match2Pay\Logger;

class API {
	/**
	 * @var API
	 */
	private static $instance;
	protected $logger;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
