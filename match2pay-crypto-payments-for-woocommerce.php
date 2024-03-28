<?php
/**
 * Plugin Name: Match2pay crypto payments for WooCommerce
 * Plugin URI: https://match2pay.com/woocommerce/payment-gateway-plugin.html
 * Description: Accept Bitcoin, USDT i.e. TRC20, Ethereum, Litecoin, Ripple, Dogecoin and other Cryptocurrencies via Match2pay on your WooCommerce store.
 * Author: Match2Pay
 * Author URI:
 * Text Domain: wc-match2pay-crypto-payment
 * Version: 1.1.1-beta.1
 * Requires at least: 5.5
 * Tested up to: 6.4.2
 *
 * WC requires at least: 7.0
 * WC tested up to: 8.5.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/vendor/autoload.php';
define( 'WC_MATCH2PAY_VERSION', '1.1.1-beta.1' );
define( 'WC_MATCH2PAY_UPDATER_URL', 'https://raw.githubusercontent.com/Match2pay/match2pay-crypto-payments-for-woocommerce/beta/updater/beta.json' );

final class WC_Match2Pay_Crypto_Payment {
	public const version = WC_MATCH2PAY_VERSION;

	private function __construct() {
		$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';
		if (
			in_array( $plugin_path, wp_get_active_and_valid_plugins() )
		) {
			$this->define_constants();
			$this->check_older_version();

			register_activation_hook( __FILE__, [ $this, 'activate' ] );
			add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
		}

	}

	private function define_constants(): void {
		define( 'MATCH2PAY_ID', "match2pay" );
		define( 'WC_MATCH2PAY_DEV_MODE', false );
		define( 'WC_MATCH2PAY_FILE', __FILE__ );
		define( 'WC_MATCH2PAY_PATH', __DIR__ );
		define( 'WC_MATCH2PAY_URL', plugins_url( '', WC_MATCH2PAY_FILE ) );
		define( 'WC_MATCH2PAY_ASSETS', WC_MATCH2PAY_URL . '/public/assets' );
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
        $this->appsero_init_tracker_match2pay_crypto_payments_for_woocommerce();

        new \Match2Pay\Updater();
		new \Match2Pay\Assets();
		new \Match2Pay\Match2Pay_Hooks();
		add_action( 'init', [ $this, 'register_order_status' ] );
		add_filter( 'woocommerce_payment_gateways', [ $this, 'match2pay_wc_add_gateway_class' ] );
		add_filter( 'wc_order_statuses', [ $this, 'add_order_statuses' ] );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', [
			$this,
			'add_woocommerce_valid_order_statuses_for_payment_complete'
		], 10, 2 );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', [
			$this,
			'woocommerce_valid_order_statuses_for_payment'
		], 10, 2 );
        add_action( 'woocommerce_blocks_loaded', [$this, 'register_block_payment_method'] );
        add_action('before_woocommerce_init', [$this, 'declare_cart_checkout_blocks_compatibility']);



	}


    public function register_block_payment_method() {
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            return;
        }

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new \Match2Pay\Block() );
            }
        );
    }

    public function declare_cart_checkout_blocks_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__);
        }
    }

    public function appsero_init_tracker_match2pay_crypto_payments_for_woocommerce() {

        if ( ! class_exists( 'Appsero\Client' ) ) {
            require_once __DIR__ . '/appsero/src/Client.php';
        }

        $client = new Appsero\Client( 'd3bb3482-1c28-4a43-9115-6f12d18e682d', 'Match2pay crypto payments for WooCommerce', __FILE__ );

        // Active insights
        $client->insights()->init();
    }

    public function register_order_status() {
		register_post_status( 'wc-partially-paid', array(
			'label'                     => 'Partially Paid',
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Partially Paid (%s)', 'Partially Paid (%s)' )
		) );
	}

	public function add_order_statuses( $order_statuses ) {
		$order_statuses['wc-partially-paid'] = _x( 'Partially Paid', 'Order status', 'woocommerce' );

		return $order_statuses;
	}

	public function woocommerce_valid_order_statuses_for_payment( $statuses, $order ) {
		$statuses[] = 'partially-paid';

		return $statuses;
	}

	public function add_woocommerce_valid_order_statuses_for_payment_complete( $statuses, $order ) {
		$statuses[] = 'partially-paid';

		return $statuses;
	}

	public function match2pay_wc_add_gateway_class( $gateways ) {
		$gateways[] = new Match2Pay\WooCommerce\Payment_Gateway();

		return $gateways;
	}

	public function activate() {
		$checkWC = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );

		if ( ! $checkWC ) {

		} else {

		}
	}
}


function wc_match2pay_init() {
	return WC_Match2Pay_Crypto_Payment::init();
}

wc_match2pay_init();
