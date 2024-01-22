<?php

namespace Match2Pay;

use Match2Pay\Logger;
use Match2Pay\WooCommerce\Payment_Gateway;
use WC_Order_Query;
use WP_Error;
use WP_REST_Request;

class REST {

	/**
	 * @var API
	 */
	protected $api;
	protected $logger;

	/**
	 *
	 * @param API $api
	 */
	public function __construct( $api ) {
		$this->api = $api;
	}

	public function rest_api_init() {

		register_rest_route(
			'match2pay/v2',
			'/match2pay_webhook',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_api_webhook_update' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function format_amount( $amount ) {
		return number_format( $amount, 8, '.', '' );
	}

	public function handle_api_webhook_update( WP_REST_Request $request ) {
		global $wpdb;
		$match2pay = new Payment_Gateway();

		$api_token  = $match2pay->api_token;
		$api_secret = $match2pay->api_secret;

		$this->logger = Logger::get_instance();
		$debugLoged   = $match2pay->get_option( 'debug_log' ) === 'yes';

		// Load necessary plugin settings
		$this->logger->write_log( 'webhook_update(): Received payment update notification. Status = ' . $request->get_param( 'status' ), $debugLoged );
		$this->logger->write_log( 'webhook_update(): - Headers = ' . wc_print_r( $request->get_headers(), true ), $debugLoged );
		$this->logger->write_log( 'webhook_update(): - Params = ' . wc_print_r( $request->get_params(), true ), $debugLoged );
		$this->logger->write_log( 'webhook_update(): - Body = ' . wc_print_r( $request->get_body(), true ), $debugLoged );
		$this->logger->write_log( 'webhook_update(): valid endpoint token, processing received webhook data...', $debugLoged );

		$callback_data = $request->get_body();
		$callback_data = json_decode( $callback_data, true );

		$paymentId           = $callback_data['paymentId'];
		$paymentStatus       = $callback_data['status'];
		$transactionCurrency = $callback_data['transactionCurrency'];
		$transactionAmount   = $this->format_amount( $callback_data['transactionAmount'] );

		$callback_signature = $request->get_header( 'signature' );
		$this->logger->write_log( 'webhook_update(): $paymentId. ' . $paymentId, $debugLoged );

		$order_id = $match2pay->get_order_by_payment_id($paymentId);

		if ( ! $order_id ) {
			$this->logger->write_log( 'webhook_update(): $order_id is null. ' . json_encode( $order_id ), $debugLoged );
			return;
		}

		$order = wc_get_order( $order_id );
		$order->update_meta_data( 'match2pay_callback', $request->get_body() );

		$signatureStr = "{$transactionAmount}{$transactionCurrency}{$paymentStatus}{$api_token}{$api_secret}";
		$signature    = hash( 'sha384', $signatureStr );

		if ( ! isset( $callback_signature ) ) {
			$this->logger->write_log( 'Bad request signature', $debugLoged );
			return new WP_Error(
				'bad_signature',
				'Bad signature',
				[ 'status' => 403 ]
			);
		}

		if ( $signature !== $callback_signature ) {
			$this->logger->write_log( 'webhook_update(): Signature is invalid', $debugLoged );
			return new WP_Error(
				'signature_mismatch',
				'Signature mismatch',
				[ 'status' => 401 ]
			);
		}

		$this->logger->write_log( 'webhook_update(): Signature is valid' );

		$payment_data = json_decode( $request->get_body(), true );
		$match2pay->save_payment_response( $payment_data );
		$match2pay->update_order_status( $payment_data );

		return array(
			'status' => 'ok',
			'msg'    => 'Payment update notification well received and processed.',
		);
	}
}
