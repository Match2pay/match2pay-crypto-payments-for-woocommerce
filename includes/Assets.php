<?php

namespace Match2Pay;
use Match2Pay\WooCommerce\Payment_Gateway;

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

		$this->payment_scripts();
	}

	public function payment_scripts() {

		$match2pay = new Payment_Gateway();
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( 'no' === $match2pay->enabled ) {
			return;
		}

		if ( empty( $match2pay->api_token ) || empty( $match2pay->api_secret ) ) {
			return;
		}

		if ( ! $match2pay->testmode && ! is_ssl() ) {
			return;
		}

		wp_enqueue_script( 'woocommerce_match2pay' );
		wp_enqueue_script( 'woocommerce_match2pay_qrcode' );
	}
}
