<?php

namespace Match2Pay;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Match2Pay\WooCommerce\Payment_Gateway;
use Match2Pay\Logger;
use WC_AJAX;

final class Block extends AbstractPaymentMethodType {

	private $gateway;
	private $logger;
	private $debugLog = true;
	protected $name   = 'wc-match2pay';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_wc-match2pay_settings', array() );
		$this->gateway  = new Payment_Gateway();
		$this->logger   = new Logger();
	}

	public function is_active() {
		return true;

		return $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-match2pay-blocks-integration',
			plugin_dir_url( WC_MATCH2PAY_FILE ) . 'build/block/checkout.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			null,
			true
		);
		// if ( function_exists( 'wp_set_script_translations' ) ) {
		// wp_set_script_translations( 'wc-match2pay-blocks-integration', 'wc-match2pay', 'languages/' );
		// }

		return array( 'wc-match2pay-blocks-integration' );
	}

	public function get_enable_for_virtual() {
		return true;
		// return $this->gateway->enable_for_virtual;
	}

	public function get_enable_for_methods() {
		return true;
		// return $this->gateway->enable_for_virtual;
	}

	public function get_payment_method_data() {
		$this->logger->write_log( 'get_payment_method_data() :' . json_encode( $this ), $this->debugLog );
		$orderId                             = WC()->session->get( 'match2pay_orderId' );
		$paymentId                           = WC()->session->get( 'match2pay_paymentId' );
		$currency                            = WC()->session->get( 'match2pay_paymentGatewayName' );
		$session_match2pay_carts_totals_hash = WC()->session->get( 'match2pay_carts_totals_hash' );
		$total_cache                         = $this->gateway->carts_totals_hash();

		if ( $session_match2pay_carts_totals_hash !== $total_cache ) {
			WC()->session->set( 'match2pay_orderId', null );
			WC()->session->set( 'match2pay_paymentId', null );
			WC()->session->set( 'match2pay_carts_totals_hash', null );
			WC()->session->set( 'match2pay_paymentGatewayName', null );
			$paymentId = null;
			$orderId   = null;
			$currency  = null;
		}

		$currencies               = $this->gateway->get_active_currencies();
		$is_single_currency_class = count( $currencies ) === 1 ? 'single-currency' : '';

		return array(
			'title'                               => $this->gateway->title,
			'description'                         => $this->gateway->description,
			'orderId'                             => $orderId,
			'paymentId'                           => $paymentId,
			'currency'                            => $currency,
			'currencies'                          => $currencies,
			'is_single_currency_class'            => $is_single_currency_class,
			'session_match2pay_carts_totals_hash' => $session_match2pay_carts_totals_hash,
			'form'                                => $this->gateway->display_embedded_payment_form_button( '' ),
			'enableForVirtual'                    => $this->get_enable_for_virtual(),
			'enableForShippingMethods'            => $this->get_enable_for_methods(),
			'supports'                            => $this->get_supported_features(),
			'watcher_interval'                    => 30000,
			'endpoints'                           => array(
				'wc_match2pay_get_payment_form_data' => array(
					'url' => wp_nonce_url( WC_AJAX::get_endpoint( 'wc_match2pay_get_payment_form_data' ), '_wc_match2pay_get_payment_form_data' ),
				),
				'wc_match2pay_start_checkout'        => array(
					'url' => wp_nonce_url( WC_AJAX::get_endpoint( 'wc_match2pay_start_checkout' ), '_wc_match2pay_start_checkout_nonce' ),
				),
				'wc_match2pay_watcher'               => array(
					'url' => wp_nonce_url( WC_AJAX::get_endpoint( 'wc_match2pay_watcher' ), '_wc_match2pay_watcher' ),
				),
			),
		);
	}
}
