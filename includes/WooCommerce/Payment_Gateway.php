<?php

namespace Match2Pay\WooCommerce;

use WC_Payment_Gateway;
use WC_AJAX;
use \Datetime;
use WC_HTTPS;
use Match2Pay\Logger;
use Match2Pay\API;
use Match2Pay\REST;

class Payment_Gateway extends WC_Payment_Gateway {
	protected $logger;
	protected $debugLog;

	public function __construct() {
		$this->logger = Logger::get_instance();

		$this->id                 = MATCH2PAY_ID;
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = 'Match2Pay Gateway';
		$this->method_description = 'Description of Match2Pay payment gateway';

		$this->supports = array(
			'products'
		);

		$this->init_form_fields();

		$this->init_settings();
		$this->debugLog     = $this->get_option( 'debug_log' ) === 'yes';
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->enabled      = $this->get_option( 'enabled' );
		$this->api_base_url = $this->get_option( 'api_base_url' );
		$this->testmode     = 'yes' === $this->get_option( 'testmode' );

		if ( $this->testmode ) {
			$this->api_base_url = 'https://pp-staging.fx-edge.com/';
		} else {
			$this->api_base_url = 'https://m2p2.match-trade.com/';
		}

		$this->api_token  = $this->testmode ? $this->get_option( 'test_api_token' ) : $this->get_option( 'api_token' );
		$this->api_secret = $this->testmode ? $this->get_option( 'test_api_secret' ) : $this->get_option( 'api_secret' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}


	public function payment_scripts() {

		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( 'no' === $this->enabled ) {
			return;
		}

		if ( empty( $this->api_token ) || empty( $this->api_secret ) ) {
			return;
		}

		if ( ! $this->testmode && ! is_ssl() ) {
			return;
		}

		wp_enqueue_script( 'woocommerce_match2pay' );
		wp_enqueue_script( 'woocommerce_match2pay_qrcode' );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'       => 'Enable/Disable',
				'label'       => 'Enable Match2Pay Gateway',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'           => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'Cryptocurrency',
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => '',
			),
			'api_secret'      => array(
				'title' => 'Live Api Secret Key',
				'type'  => 'password'
			),
			'api_token'       => array(
				'title' => 'Live Api Token Key',
				'type'  => 'password'
			),
			'testmode'        => array(
				'title'       => 'Test mode',
				'label'       => 'Enable Test Mode',
				'type'        => 'checkbox',
				'description' => 'Place the payment gateway in test mode using test API keys.',
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_api_secret' => array(
				'title' => 'Test Api Secret Key',
				'type'  => 'password'
			),
			'test_api_token'  => array(
				'title' => 'Test Api Token Key',
				'type'  => 'password',
			),
		);

		$this->form_fields['currency_section_separator1'] = array(
			'title'       => '',
			'type'        => 'title',
			'description' => '',
			'class'       => 'wc-settings-separator',
		);

		$this->form_fields['currency_section_separator2'] = array(
			'title'       => __( 'Currency Options', 'your-text-domain' ),
			'type'        => 'title',
			'description' => '',
			'class'       => 'wc-settings-separator',
		);

		$currencies = $this->get_match2pay_currencies();



		foreach ( $currencies as $code => $currency ) {
			$sanitized_code = sanitize_file_name( $code );
			$this->form_fields[ $sanitized_code . '_enabled' ]    = array(
				'title'       => $currency['paymentCurrency'] . ' Enable/Disable',
				'label'       => 'Enable ' . $currency['paymentCurrency'],
				'type'        => 'checkbox',
				'description' => 'Enable or disable ' . $currency['paymentCurrency'] . ' payments',
				'default'     => 'no',
			);
//			$this->form_fields[ $sanitized_code . '_min_amount' ] = array(
//				'title'       => $currency['paymentCurrency'] . ' Minimum Amount',
//				'type'        => 'number',
//				'description' => 'Set minimum amount for ' . $currency['paymentCurrency'],
//				'default'     => $currency['min_amount'],
//			);
		}

	}

	public function get_match2pay_currencies() {
		return [
			'BTC'        => [
				'paymentCurrency' => 'BTC',
				'min_amount'      => 0.0001
			],
			'USDT ERC20' => [
				'paymentCurrency' => 'UST',
				'min_amount'      => 1
			],
			'USDC ERC20' => [
				'paymentCurrency' => 'UCC',
				'min_amount'      => 1
			],
			'ETH'        => [
				'paymentCurrency' => 'ETH',
				'min_amount'      => 0.0001
			],
			'USDT BEP20' => [
				'paymentCurrency' => 'USB',
				'min_amount'      => 0.0001
			],
			'USDC BEP20' => [
				'paymentCurrency' => 'USB',
				'min_amount'      => 1
			],
			'BNB'        => [
				'paymentCurrency' => 'BNB',
				'min_amount'      => 0.1
			],
			'USDT TRC20' => [
				'paymentCurrency' => 'USX',
				'min_amount'      => 1
			],
			'USDC TRC20' => [
				'paymentCurrency' => 'UCX',
				'min_amount'      => 1
			],
			'TRX'        => [
				'paymentCurrency' => 'TRX',
				'min_amount'      => 1
			],
		];
	}


	public function get_active_currencies() {
		$currencies        = $this->get_match2pay_currencies();
		$active_currencies = [];

		foreach ( $currencies as $code => $currency ) {
			$sanitized_code = sanitize_file_name( $code );
			// Check if the currency is enabled in the settings
			if ( 'yes' === $this->get_option( $sanitized_code . '_enabled' ) ) {
				$active_currencies[ $code ] = $currency;
			}
		}

		return $active_currencies;
	}

	public function payment_fields() {
		echo $this->get_description( '' );
		echo $this->display_embedded_payment_form_button( '' );
		echo "<!-- anti-checkout.js-fragment-cache '" . $this->carts_totals_hash() . "' -->";
	}

	public function carts_totals_hash() {
		$cart_totals_hash = ( ! empty( WC()->cart->get_cart_contents_total() ) ? WC()->cart->get_cart_contents_total() : '2' ) . '_' . ( ! empty( WC()->cart->get_cart_discount_total() ) ? WC()->cart->get_cart_discount_total() : '3' ) . '_' . ( ! empty( WC()->cart->get_cart_shipping_total() ) ? WC()->cart->get_cart_shipping_total() : '4' );

		return md5( $cart_totals_hash );
	}

	public static function match2pay_ajax_get_payment_form_data() {

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], '_wc_match2pay_get_payment_form_data' ) ) {
			wp_die( __( 'Bad attempt, invalid nonce for payment form data request', 'wc-match2pay-crypto-payment' ) );
		}

		$match2pay = new Payment_Gateway();

		$is_data_expired = false;

		$payment_form_data = $match2pay->get_payment_form_request( null );

		if ( is_string( $payment_form_data ) ) {
			$payment_form_data = json_decode( $payment_form_data );
		}


		if ( isset( $payment_form_data->error ) || ! isset( $payment_form_data->checkoutUrl ) ) {
			$match2pay->logger->write_log( 'Error. Ajax payment form request failed', $match2pay->debugLog );
			echo json_encode(
				[
					'status'  => 'failed',
					'code'    => isset( $payment_form_data->code ) ? $payment_form_data->code : 'Unknown error code.',
					'message' => isset( $payment_form_data->message ) ? $payment_form_data->message : 'Unknown error message.',
					'error'   => isset( $payment_form_data->error ) ? $payment_form_data->error : 'Unknown error.',
				]
			);

			return;
		}

		$match2pay->logger->write_log( 'Ajax payment form request succeeded', $match2pay->debugLog );

		// TODO ! Get this from session during process_payment order placing call.

		$post_data          = $_POST;
		$paymentGatewayName = $post_data['match2pay_currency'];
		WC()->session->set( 'match2pay_paymentId', $payment_form_data->paymentId );
		WC()->session->set( 'match2pay_paymentGatewayName', $paymentGatewayName );
		WC()->session->set( 'match2pay_carts_totals_hash', $match2pay->carts_totals_hash() );

		// TODO verify payment status, make sure the session's data hasn't expired yet..
		$match2pay->logger->write_log( 'Checking payment status to make sure we dont use expired cached form data', $match2pay->debugLog );

		if ( $is_data_expired ) {
			$match2pay->logger->write_log( 'Cached payment status has expired. Resetting form data to force refresh.', $match2pay->debugLog );
		} else {
			$match2pay->logger->write_log( 'Payment status data is up-to-date, ready to use for the checkout page.', $match2pay->debugLog );
		}

		echo json_encode(
			[
				'status'            => 'ok',
				'message'           => 'Payment form data ready.',
				'payment_form_data' => $payment_form_data,
			]
		);
	}

	public static function match2pay_start_checkout() {
		$self = new Payment_Gateway();
		$self->logger->write_log( 'match2pay_start_checkout() called.', $self->debugLog );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], '_wc_match2pay_start_checkout_nonce' ) ) {
			$self->logger->write_log( 'match2pay_start_checkout() ERROR: wrong nonce.', $self->debugLog );
			wp_die( __( 'Bad attempt, invalid nonce for checkout_start', 'wc-match2pay-crypto-payment' ) );
		}

		add_action( 'woocommerce_after_checkout_validation', [
			self::class,
			'match2pay_checkout_check',
		], PHP_INT_MAX, 2 );
		$order_id = get_query_var( 'order-pay' );
		WC()->checkout->process_checkout();
	}

	public static function match2pay_checkout_check( $data, $errors = null ) {

		$self = new Payment_Gateway();
		$self->logger->write_log( 'match2pay_checkout_check() called.', $self->debugLog );


		if ( is_null( $errors ) ) {
			$self->logger->write_log( 'match2pay_checkout_check() Form errors found.', $self->debugLog );
			// Compatibility with WC <3.0: get notices and clear them so they don't re-appear.
			$error_messages = wc_get_notices( 'error' );
			wc_clear_notices();
		} else {
			$error_messages = $errors->get_error_messages();
		}

		if ( empty( $error_messages ) ) {
			$self->logger->write_log( 'match2pay_checkout_check() success.', $self->debugLog );

			if ( has_action( 'woocommerce_checkout_process' ) ) {

				$self->logger->write_log( 'match2pay_checkout_check() site has custom validation on woocommerce_checkout_process hoook.', $self->debugLog );

				// Run the custom validation
				do_action( 'woocommerce_checkout_process' );

				$error_messages = $errors->get_error_messages();

				$wc_notices = wc_get_notices();

				// Merge the WooCommerce notices with the validation errors
				$error_messages = array_merge( $error_messages, $wc_notices );
				foreach ( $error_messages as $message ) {
					$errors->add( 'validation', $message );
				}
				wc_clear_notices();
				if ( ! empty( $error_messages ) ) {

					if ( isset( $error_messages['error'] ) && count( $error_messages['error'] ) > 1 ) {
						$map = [];
						$dup = [];
						foreach ( $error_messages['error'] as $key => $val ) {
							if ( ! array_key_exists( $val['notice'], $map ) ) {
								$map[ $val['notice'] ] = $key;
							} else {
								$dup[] = $key;
							}
						}
						foreach ( $dup as $key => $val ) {
							unset( $error_messages['error'][ $val ] );
						}
						sort( $error_messages['error'] );
					}
					$self->logger->write_log( 'match2pay_checkout_check() custom validation: Form errors found.', $self->debugLog );
					//$self->logger->write_log(json_encode($error_messages), $self->debugLog );
					$self->logger->write_log( 'match2pay_checkout_check()  custom validationfailed.', $self->debugLog );
					wp_send_json_error(
						[
							'messages' => $error_messages,
							'status'   => 'notok',
							'from'     => 'match2pay_checkout_check custom validation'
						]
					);
				}

			}
			wp_send_json_success(
				[
					'status' => 'ok',
				]
			);
		} else {
			$self->logger->write_log( 'match2pay_checkout_check(): Form errors found.', $self->debugLog );
			$self->logger->write_log( 'match2pay_checkout_check() failed.', $self->debugLog );
			wp_send_json_error(
				[
					'messages' => $error_messages,
					'status'   => 'notok',
					'from'     => 'match2pay_checkout_check',
				]
			);
		}
		exit;
	}

	public function display_currency_select( $echo = true ) {
		$currencies = $this->get_active_currencies();
		$output     = '';

		if ( count( $currencies ) == 1 ) {
			$currency = array_values( $currencies )[0];
			$output   .= '<input type="hidden" name="match2pay_currency" value="' . array_keys( $currencies )[0] . '">';
			$output   .= '<input type="hidden" name="match2pay_currency_name" value="' . $currency['paymentCurrency'] . '">';
		} else {
			$select_currency_text = __( 'Select Payment Cryptocurrency', 'wc-match2pay-crypto-payment' );
			$output               .= '<label>' . $select_currency_text . ' <span class="required">*</span></label>';
			$output               .= '<select id="match2pay_currency" name="match2pay_currency" class="select2" style="min-width: 150px;">';
			$output               .= '<option value="">' . __( 'Select currency', 'wc-match2pay-crypto-payment' ) . '</option>';
			foreach ( $currencies as $code => $currency ) {
				$output .= '<option value="' . $code . '">' . $currency['paymentCurrency'] . '</option>';
			}
			$output .= '</select>';
		}

		if ( $echo ) {
			echo $output;
		} else {
			return $output;
		}
	}

	public function display_embedded_payment_form_button( $button_html ) {
		$output = '';

		$nonce_action               = '_wc_match2pay_get_payment_form_data';
		$paymentform_ajax_url       = WC_AJAX::get_endpoint( 'wc_match2pay_get_payment_form_data' );
		$paymentform_ajax_nonce_url = wp_nonce_url( $paymentform_ajax_url, $nonce_action );
		$output_paymentform_url     = '<div id="match2pay-payment-gateway-payment-form-request-ajax-url" data-value="' . $paymentform_ajax_nonce_url . '" style="display:none;"></div>';

		$nonce_action              = '_wc_match2pay_start_checkout_nonce';
		$start_checkout_url        = WC_AJAX::get_endpoint( 'wc_match2pay_start_checkout' );
		$start_checkout_nonce_url  = wp_nonce_url( $start_checkout_url, $nonce_action );
		$output_startcheckoutcheck = "<div id='match2pay-payment-gateway-start-checkout-check-url' style='display:none;' data-value='$start_checkout_nonce_url'></div>";

		$nonce_action            = '_wc_match2pay_watcher';
		$start_watcher           = WC_AJAX::get_endpoint( 'wc_match2pay_watcher' );
		$start_watcher_nonce_url = wp_nonce_url( $start_watcher, $nonce_action );
		$output_start_watcher    = "<div id='match2pay-payment-gateway-watcher' style='display:none;' data-value='$start_watcher_nonce_url'></div>";

		$orderID = '';
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$order   = wc_get_order( get_query_var( 'order-pay' ) );
			$orderID = $order->get_id();
		}


		$paymentId                           = WC()->session->get( 'match2pay_paymentId' );
		$session_match2pay_carts_totals_hash = WC()->session->get( 'match2pay_carts_totals_hash' );
		$total_cache                         = $this->carts_totals_hash();

		if ( $session_match2pay_carts_totals_hash !== $total_cache ) {
			WC()->session->set( 'match2pay_paymentId', null );
			WC()->session->set( 'match2pay_carts_totals_hash', null );
			WC()->session->set( 'match2pay_paymentGatewayName', null );
			$paymentId = null;
		}

		$currencies = $this->get_active_currencies();

		$is_single_currency_class       = count( $currencies ) === 1 ? "single-currency" : "";
		$order_pay_checkout_class = ( is_wc_endpoint_url( 'order-pay' ) ) ? ' match2pay-order-pay' : '';
		$order_button_text        = __( 'Pay with Cryptocurrency', 'wc-match2pay-crypto-payment' );
		$output                   .= '<div class="match2pay-payment-setting ' . $is_single_currency_class . '">';
		$output                   .= '<div class="match2pay-row">';
		$output                   .= $this->display_currency_select( false );
		$output                   .= '</div>';
		$output                   .= '<button type="button"
        class="button alt match2pay-pay-with' . $order_pay_checkout_class . '"
        onclick="match2pay_validateCheckout(this)"
        name="match2pay_embedded_payment_form_btn"
        id="match2pay_embedded_payment_form_btn"
        value="' . esc_attr( $order_button_text ) . '"
        data-id="' . $orderID . '"
        data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>';
		$output                   .= '</div>';

		$output .= '<div id="match2pay_embedded_payment_form_loading_txt"></div>';
		$output .= '<div id="match2pay-payment-form" data-payment-id="' . $paymentId . '">';
		$output .= '<div id="match2pay-qr"></div>';
		$output .= '<div id="match2pay-details"></div>';
		$output .= '</div>';

		return $button_html . $output . $output_paymentform_url . $output_startcheckoutcheck . $output_start_watcher;
	}

	public function get_transaction_data( $paymentId ) {
		$url    = $this->api_base_url . 'api/ui/public/payments/' . $paymentId;
		$result = wp_remote_get( $url );

		return json_decode( wp_remote_retrieve_body( $result ) );
	}

	public static function match2pay_ajax_payment_watcher() {
		$match2pay = new Payment_Gateway();

		if ( $order_id != null ) {
			$order          = wc_get_order( $order_id );
			$order_amount   = esc_attr( ( ( WC()->version < '2.7.0' ) ? $order->order_total : $order->get_total() ) );
			$order_currency = esc_attr( ( ( WC()->version < '2.7.0' ) ? $order->order_currency : $order->get_currency() ) );
		} else {
			$order_amount   = esc_attr( WC()->cart->total );
			$order_currency = esc_attr( strtoupper( get_woocommerce_currency() ) );
		}

		$response = $match2pay->get_transaction_data( $_POST['match2pay_paymentId'] );

		$response->order_amount   = (float) $order_amount;
		$response->order_currency = $order_currency;
		$response->is_enough      = ( $response->final->amount >= $response->order_amount ) && $response->paymentStatus === 'COMPLETED';

		$paymentGatewayName           = WC()->session->get( 'match2pay_paymentGatewayName' );
		$response->paymentGatewayName = $paymentGatewayName ?? 'Unknown';

		wp_send_json_success( $response );
	}

	/**
	 * Payment request function for order pay page
	 * TODO: ??
	 */
	public static function match2pay_orderpay_payment_request() {
		throw new \Exception( 'Not implemented' );
		$orderID = $_POST['orderid'];
//		$order      = wc_get_order( $orderID );
//		$order_data = $order->get_data();
//
//		$match2pay = new Payment_Gateway();
//
//		$payment_form_data = $match2pay->get_payment_form_request(
//			$orderID,
//		);
//
//		$hostedURL = $payment_form_data->hosted_url;
//		echo $hostedURL;
//		wp_die();
	}

	private function get_payment_form_request( $order_id ) {
		$self = new Payment_Gateway();

		$post_url = $this->api_base_url . 'api/v2/deposit/crypto_agent';

		$body = $this->preparePaymentFormRequestBody(
			$order_id,
		);

		$self->logger->write_log( 'Making a payment form API request with body: ' . wc_print_r( $body, true ), $self->debugLog );

		$result = wp_remote_post( $post_url, [
			'method'      => 'POST',
			'headers'     => [
				'Content-Type' => 'application/json; charset=utf-8',
			],
			'body'        => json_encode( $body ),
			'data_format' => 'body',
		] );


		if ( is_wp_error( $result ) ) {
			return [ 'error' => 'Error happened, could not complete the payment form request.' ];
		}
		$self->logger->write_log( 'Payment form request response: \n' . wc_print_r( $result['body'], true ), $self->debugLog );


		if ( $result['response']['code'] > 299 ) {
			return json_encode( [
				'error'   => 'Error happened, could not complete the payment form request.',
				'code'    => $result['response']['code'],
				'message' => $result['response']['message'],
			] );
		}

		$json_result = json_decode( $result['body'] );

		if ( ! isset( $json_result->checkoutUrl ) ) {
			return json_encode( [
				'error' => 'Error happened, wrong payment form request data format received.',
			] );
		}

		return $json_result;
	}


	private function preparePaymentFormRequestBody( $order_id ) {
		if ( $order_id != null ) {
			$order = wc_get_order( $order_id );

			$this->logger->write_log( 'Order Pay Page Cart Total:' . $order->get_total(), $this->debugLog );

			$order_amount   = esc_attr( ( ( WC()->version < '2.7.0' ) ? $order->order_total : $order->get_total() ) );
			$order_currency = esc_attr( ( ( WC()->version < '2.7.0' ) ? $order->order_currency : $order->get_currency() ) );
		} else {
			$order_amount   = esc_attr( WC()->cart->total );
			$order_currency = esc_attr( strtoupper( get_woocommerce_currency() ) );
		}

		$this->logger->write_log( 'Order WC()->cart->total: ' . WC()->cart->total, $this->debugLog );
		$this->logger->write_log( 'Order WC()->cart->get_cart_total(): ' . WC()->cart->get_cart_total(), $this->debugLog );
		$this->logger->write_log( 'Order WC()->cart->get_cart_contents_total(): ' . WC()->cart->get_cart_contents_total(), $this->debugLog );
		$this->logger->write_log( 'Order WC()->cart->get_cart_discount_total(): ' . WC()->cart->get_cart_discount_total(), $this->debugLog );
		$this->logger->write_log( 'Order WC()->cart->get_cart_shipping_total(): ' . WC()->cart->get_cart_shipping_total(), $this->debugLog );


		$callback_url = get_rest_url( null, 'match2pay/v2/match2pay_webhook/' . get_option( 'match2pay_api_endpoint_token' ) );

		$currencies         = $this->get_match2pay_currencies();
		$api_token          = $this->api_token;
		$api_secret         = $this->api_secret;
		$post_data          = $_POST;
		$paymentGatewayName = $post_data['match2pay_currency'];

		$match2pay_data = [
			"amount"             => $order_amount,
			"currency"           => $order_currency,
			"paymentGatewayName" => $paymentGatewayName,
			"paymentCurrency"    => $currencies[ $paymentGatewayName ]['paymentCurrency'],
			"callbackUrl"        => $callback_url,
			"apiToken"           => $api_token,
			"timestamp"          => time(),
		];
		ksort( $match2pay_data );

		$signature                   = implode( $match2pay_data );
		$signature                   = hash( 'sha384', "{$signature}{$api_secret}" );
		$match2pay_data['signature'] = $signature;

		return $match2pay_data;
	}

	//TODO: refactor
	public function validate_fields() {

		if ( empty( $_POST['billing_first_name'] ) ) {
			wc_add_notice( 'First name is required!', 'error' );

			return false;
		}

		return true;

	}

	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$amount = $order->get_total();

		$paymentId        = $_POST['match2pay_paymentId'];
		$sessionPaymentId = WC()->session->get( 'match2pay_paymentId' );
		$order->update_meta_data( 'match2pay_paymentId', $paymentId );

		if ( $paymentId !== $sessionPaymentId ) {
			$order->set_status( 'failed' );
			throw new \Exception( 'Payment ID mismatch' );
		}

		WC()->session->set( 'match2pay_paymentId', null );
		$payment_data_callback = $this->get_payment_response( $paymentId );

		if ( $payment_data_callback ) {
			$this->logger->write_log( 'process_payment(): Process payment with cached callback data: ' . json_encode( $payment_data_callback ), $this->debugLog );
			$this->update_order_status( $payment_data_callback );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		$order_amount = apply_filters( 'wc_match2pay_order_amount', $amount, $order->get_currency(), $order->get_id() );
		$payment_data = $this->get_transaction_data( $paymentId );

		$this->logger->write_log( 'process_payment(): Process payment with ui data: ' . json_encode( $payment_data ), $this->debugLog );
		if ( $payment_data->final->amount >= $order_amount ) {
			$order->payment_complete();
			$order->reduce_order_stock();
		} else {
			$order->set_status( 'failed' );
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}


	public function get_title() {
		if ( ! empty( $this->get_option( 'crypto_text' ) ) ) {
			$title_text = stripcslashes( $this->get_option( 'crypto_text' ) );
			$title      = __( $title_text, 'wc-match2pay-crypto-payment' );
		} else {
			$title = __( 'Cryptocurrency', 'wc-match2pay-crypto-payment' );
		}

		return apply_filters( 'woocommerce_gateway_title', $title, $this->id );
	}

	//TODO: refactor function location
	public function save_payment_response( $payment_data ) {
		set_transient( 'match2pay_paymentId_' . $payment_data['paymentId'], $payment_data, 60 * 60 * 24 * 7 );
	}

	public function get_payment_response( $payment_id ) {
		return get_transient( 'match2pay_paymentId_' . $payment_id );
	}

	public function remove_payment_response( $payment_id ) {
		delete_transient( 'match2pay_paymentId_' . $payment_id );
	}

	public function update_order_status( $callback_data ) {
		global $wpdb;

		try {
			$paymentId     = $callback_data['paymentId'];
			$paymentStatus = $callback_data['status'];
			$results       = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}wc_orders_meta WHERE meta_value = '{$paymentId}' AND meta_key = 'match2pay_paymentId'" );

			if ( ! $results ) {
				return;
			}

			$order = wc_get_order( $results->order_id );
			$order->update_meta_data( 'match2pay_callback', $callback_data );
			$this->logger->write_log( 'match2pay_callback' . json_encode( $callback_data ), $this->debugLog );

			if ( ! $order ) {
				return;
			}

			if ( $paymentStatus !== 'DONE' ) {
				return;
			}

			$this->logger->write_log( json_encode( $results ) );
			$this->logger->write_log( json_encode( $order->get_status() ) );

			if ( $callback_data['status'] !== 'DONE' ) {
				return;
			}

			if ( $order->get_status() === 'pending' ) {

				//TODO: refactor formatting
				if ( ! strpos( $callback_data['transactionAmount'], '.' ) ) {
					$callback_data['transactionAmount'] = "{$callback_data['transactionAmount']}.00000000";
				} elseif ( strlen( explode( '.', $callback_data['transactionAmount'] )[1] ) < 8 ) {
					$callback_data['transactionAmount'] = $callback_data['transactionAmount'] . str_pad( '', 8 - strlen( explode( '.', $callback_data['transactionAmount'] )[1] ), '0' );
				}

				$order_amount = $order->get_total();
				$order_amount = apply_filters( 'wc_match2pay_order_amount', $order_amount, $order->get_currency(), $order->get_id() );

				if ( $callback_data['finalAmount'] >= $order_amount ) {
					$order->payment_complete();
					wc_reduce_stock_levels( $order->get_id() );
				}
			} else {
				$this->logger->write_log( 'Order status is not pending', $this->debugLog );
			}
			$this->remove_payment_response( $paymentId );
		} catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}
}
