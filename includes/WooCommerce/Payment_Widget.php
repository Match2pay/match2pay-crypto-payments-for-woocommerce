<?php

namespace Match2Pay\WooCommerce;

use WC_Order;

function accentText( $text ) {
	return '<span class="match2pay-accent">' . htmlspecialchars( $text ) . '</span>';
}

class Payment_Widget {

	public function format_rate( $rate ) {
		return number_format( (float) $rate, 4, '.', '' );
	}

	public function get_status_text( $status ) {
		$statusText = array(
			'STARTED'        => 'Waiting for payment',
			'PENDING'        => 'Waiting for confirmations',
			'COMPLETED'      => 'Payment completed',
			'PARTIALLY_PAID' => 'Partially paid',
		);

		return $statusText[ $status ] ?? 'Unknown';
	}

	public function render_html( $order_id ) {
		$match2pay = new Payment_Gateway();

		$data = $match2pay->get_transaction_data_by_order( $order_id );
		$html = '';

		$accentText = function ( $text ) {
			return '<span class="match2pay-accent">' . htmlspecialchars( $text ) . '</span>';
		};

		$paymentAddress     = htmlspecialchars( $data->walletAddress );
		$paymentGatewayName = htmlspecialchars( $data->paymentGatewayName );
		$paymentStatus      = $data->paymentStatus;
		$copyIcon           = WC_MATCH2PAY_ASSETS . '/img/copy-outline-icon.svg';

		$html .= '<div class="match2pay-payment-details">';

		if ( 'STARTED' === $paymentStatus ) {
			$amount           = htmlspecialchars( $data->final->amount );
			$currency         = htmlspecialchars( $data->final->currency );
			$paymentFinal     = $amount . ' ' . $currency;
			$transactionFinal = htmlspecialchars( $data->transaction->amount ) . ' ' . $paymentGatewayName;
			$description      = 'To make a ' . $accentText( $paymentFinal ) . ' deposit, please send ' . $accentText( $transactionFinal ) . ' to the address below.';
			$html            .= '<p>' . $description . '</p>';
		}

		if ( 'PARTIALLY_PAID' === $paymentStatus ) {
			$order_left_to_pay_amount = htmlspecialchars( $data->order_left_to_pay_amount );
			$currency                 = htmlspecialchars( $data->final->currency );
			$paymentFinal             = $order_left_to_pay_amount . ' ' . $currency;
			$transactionFinal         = htmlspecialchars( $data->order_left_to_pay_crypto_amount ) . ' ' . $paymentGatewayName;
			$description1             = 'Please send the remaining amount to the same address.';
			$description2             = 'To deposit ' . $accentText( $paymentFinal ) . ', send ' . $accentText( $transactionFinal ) . '.';
			$before1                  = 'We received a partial payment.';
			$before2                  = $accentText( $data->order_deposited_amount ) . ' of ' . $accentText( $data->order_amount ) . ' ' . $currency . '.';
			$html                    .= '<p>' . $before1 . '</p>';
			$html                    .= '<p style="margin-bottom: 10px;">' . $before2 . '</p>';
			$html                    .= '<p>' . $description1 . '</p>';
			$html                    .= '<p>' . $description2 . '</p>';
		}

		if ( 'PENDING' === $paymentStatus && isset( $data->transactions ) ) {
			$receivedConfirmations = htmlspecialchars( $data->transactions->confirmationOfTheLastTransaction->receivedConfirmations );
			$requiredConfirmations = htmlspecialchars( $data->transactions->confirmationOfTheLastTransaction->requiredConfirmations );
			$description           = 'We have received ' . $receivedConfirmations . ' confirmations of ' . $requiredConfirmations . ' required.<br/> Please wait for the transaction to be confirmed.';
			$html                 .= '<p>' . $description . '</p>';
		}

		if ( 'COMPLETED' === $paymentStatus ) {
			$description = 'Payment completed.';
			$html       .= '<p>' . $description . '</p>';
		}

		$conversionRate = $this->format_rate( $data->realConversionRate );

		$html .= '<p class="match2pay-wallet-address">' . $paymentAddress . '<img alt="copy" src="' . $copyIcon . '"></p>';
		$html .= '<p class="' . htmlspecialchars( $paymentStatus ) . '">' . htmlspecialchars( $this->get_status_text( $paymentStatus ) ) . '</p>';
		$html .= '<p class="match2pay-payment-conversion-rate">1 ' . $paymentGatewayName . ' = ' . $conversionRate . ' USD</p>';
		$html .= '<p class="match2pay-payment-notice">Please pay the exact amount. Avoid paying from a crypto exchange, use your personal wallet.</p>';

		$html .= '</div>';

		return $html;
	}
}
