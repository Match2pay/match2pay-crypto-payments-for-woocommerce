<?php
namespace Match2Pay\WooCommerce;

use WC_Order;

class Thank_You {

	public static function match2pay_change_order_received_text( $str, $order ) {

		if ( isset( $order ) && $order && $order->has_status( 'failed' ) ) {

			$new_str = $str . '<br>'
						. 'Your order was placed. However, your payment was either insufficient or not detected.';
			return $new_str;
		}

		return $str;
	}

	public static function thankyou_page_payment_details( $order_id ) {
		$wc_order = wc_get_order( $order_id );
		if ( empty( $wc_order ) ) {
			return;
		}

		if ( isset( $match2pay->settings['match2pay_woocommerce_order_states'] ) && isset( $match2pay->settings['match2pay_woocommerce_order_states']['paid'] ) ) {
			$order_status_invalid = $match2pay->settings['match2pay_woocommerce_order_states']['invalid'];
		} else {
			$order_status_invalid = 'wc-failed';
		}

		// TODO: more info for user
	}
}
