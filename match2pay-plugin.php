<?php
/**
 * Plugin Name: WooCommerce Match2Pay Payment Gateway
 * Plugin URI: https://match2pay.com/woocommerce/payment-gateway-plugin.html
 * Description: Description
 * Author:
 * Author URI:
 * Version: 1.0.0
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

final class WC_Match2Pay_Crypto_Payment {
    public const version = '1.0.0';

    private function __construct()
    {
        $this->define_constants();
        $this->check_older_version();

        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    private function define_constants(): void {
	    define( 'MATCH2PAY_ID', "match2pay" );
	    define( 'WC_MATCH2PAY_VERSION', self::version );
	    define( 'WC_MATCH2PAY_FILE', __FILE__ );
	    define( 'WC_MATCH2PAY_PATH', __DIR__ );
	    define( 'WC_MATCH2PAY_URL', plugins_url( '', WC_MATCH2PAY_FILE ) );
	    define( 'WC_MATCH2PAY_ASSETS', WC_MATCH2PAY_URL . '/assets' );
    }

    private function check_older_version(): void {
        // on next versions
    }

    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    public function init_plugin(): void {
        new \Match2Pay\Assets();
        new \Match2Pay\Match2Pay_Hooks();
        add_filter( 'woocommerce_payment_gateways', [ $this, 'match2pay_wc_add_gateway_class' ] );
    }

    public function match2pay_wc_add_gateway_class( $gateways ) {
        $gateways[] = new Match2Pay\WooCommerce\Payment_Gateway();
        return $gateways;
    }

	public function activate() {
		$checkWC   = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option('active_plugins') ) );

		if ( ! $checkWC ) {

		} else {

		}
	}
}

function wc_match2pay_init() {
    return WC_Match2Pay_Crypto_Payment::init();
}

wc_match2pay_init();
