<?php

namespace Match2Pay\WooCommerce;

use WC_Order;

class Admin {


	/**
	 * @param $order WC_Order
	 *
	 * @return void
	 */
	public static function display_custom_crypto_payment_details( $order ) {
		$order_id  = $order->get_id();
		$match2pay = new Payment_Gateway();

		$paymentId       = $order->get_meta( 'match2pay_paymentId' );
		$payment_details = $match2pay->get_transaction_data_by_order( $order_id );

		$status = $payment_details->paymentStatus;

		if ( ! empty( $payment_details ) ) {
			$payment_details_obj = maybe_unserialize( $payment_details );

			echo '<div class="clear"></div>';
			echo '<div>';
			echo '<h4 style="margin-top: 30px;">Crypto Payment Details</h4>';

			$url  = $match2pay->api_base_url . 'requests' . '?paymentId=' . $paymentId;
			$link = '<a href="' . $url . '">' . $paymentId . '</a>';
			echo '<p><strong>Payment Id:</strong> ' . $link . '</p>';
			echo '<p><strong>Wallet Address:</strong> ' . esc_html( $payment_details_obj->walletAddress ) . '</p>';
			echo '<p><strong>Transaction Gateway Name:</strong> ' . esc_html( $payment_details_obj->transaction->gatewayName ) . '</p>';
			echo '<p><strong>Payment Status:</strong> ' . esc_html( $payment_details_obj->paymentStatus ) . '</p>';
			echo '<p><strong>Deposited Amount:</strong> ' . esc_html( $payment_details_obj->order_deposited_amount ) . ' ' . esc_html( $payment_details_obj->final->currency ) . '</p>';

			if ( $status === 'PARTIALLY_PAID' ) {
				echo '<p><strong>Left to pay Amount:</strong> ' . esc_html( $payment_details_obj->order_left_to_pay_amount ) . ' ' . esc_html( $payment_details_obj->final->currency ) . '</p>';
			}

			if ( $status === 'COMPLETED' ) {
				echo '<p><strong>Overpay Amount:</strong> ' . esc_html( $payment_details_obj->order_overpay_amount ) . ' ' . esc_html( $payment_details_obj->final->currency ) . '</p>';
			}

			echo '<p><strong>Transaction Amount:</strong> ' . esc_html( $payment_details_obj->transaction->amount ) . ' ' . esc_html( $payment_details_obj->transaction->gatewayName ) . '</p>';
			echo '<p><strong>Conversion Rate:</strong> ' . esc_html( $payment_details_obj->realConversionRate ) . '</p>';
			echo '<p><strong>Payment Confirmed At:</strong> ' . esc_html( $payment_details_obj->paymentConfirmedAt ) . '</p>';
			echo '</div>';
		}
	}
}
