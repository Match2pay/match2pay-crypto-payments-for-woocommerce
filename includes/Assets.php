<?php

namespace Match2Pay;

class Assets {
	function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	public function register_assets() {
		wp_register_script( 'woocommerce_match2pay_qrcode', "https://cdn.jsdelivr.net/npm/qrcode-js-package@1.0.4/qrcode.min.js" );
		wp_register_style( 'woocommerce_match2pay', WC_MATCH2PAY_ASSETS . '/css/styles.css', array() );
		wp_enqueue_style( 'woocommerce_match2pay');
		wp_register_script( 'woocommerce_match2pay', WC_MATCH2PAY_ASSETS . '/js/match2pay.js', array(
			'jquery',
			'woocommerce_match2pay_qrcode'
		) );
		wp_localize_script( 'woocommerce_match2pay', 'match2pay_params', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'assets_base' => WC_MATCH2PAY_ASSETS,
		) );
	}
}
