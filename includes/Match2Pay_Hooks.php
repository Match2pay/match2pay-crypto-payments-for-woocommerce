<?php


namespace Match2Pay;

use Match2Pay\API;
use Match2Pay\REST;
use Match2Pay\WooCommerce\Thank_You;
use Match2Pay\WooCommerce\Admin;
use Match2Pay\WooCommerce\Payment_Gateway;

class Match2Pay_Hooks {

	private $api;

	public function __construct() {
		$this->api = API::get_instance();

		$this->define_woocommerce_hooks();
		$this->define_rest_hooks();
	}

	public function define_rest_hooks(): void {
		$rest = new REST( $this->api );

		add_action( 'rest_api_init', array( $rest, 'rest_api_init' ) );
	}

	public function define_woocommerce_hooks(): void {
		add_action( 'wc_ajax_wc_match2pay_start_checkout', array( Payment_Gateway::class, 'match2pay_start_checkout' ) );
		add_action(
			'wc_ajax_wc_match2pay_get_payment_form_data',
			array(
				Payment_Gateway::class,
				'match2pay_ajax_get_payment_form_data',
			)
		);
		add_action( 'wc_ajax_wc_match2pay_watcher', array( Payment_Gateway::class, 'match2pay_ajax_payment_watcher' ) );

		add_action(
			'wp_ajax_match2pay_orderpay_payment_request',
			array(
				Payment_Gateway::class,
				'match2pay_ajax_orderpay_payment_request',
			)
		);
		add_action(
			'wp_ajax_nopriv_match2pay_orderpay_payment_request',
			array(
				Payment_Gateway::class,
				'match2pay_ajax_orderpay_payment_request',
			)
		);

		add_filter(
			'woocommerce_thankyou_order_received_text',
			array(
				Thank_You::class,
				'match2pay_change_order_received_text',
			),
			10,
			2
		);
		add_action( 'woocommerce_thankyou_match2pay', array( Thank_You::class, 'thankyou_page_payment_details' ), 10, 1 );

		add_action(
			'woocommerce_admin_order_data_after_shipping_address',
			array(
				Admin::class,
				'display_custom_crypto_payment_details',
			)
		);
	}
}
