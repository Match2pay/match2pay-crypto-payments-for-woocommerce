<?php
/*
 * Plugin Name: WooCommerce Match2Pay Payment Gateway
 * Plugin URI: https://match2pay.com/woocommerce/payment-gateway-plugin.html
 * Description: Description
 * Author: UkroSoft
 * Author URI: http://ukrosoft.com
 * Version: 1.0.1
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'match2pay_add_gateway_class');
function match2pay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Match2Pay_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'match2pay_init_gateway_class');
function match2pay_init_gateway_class()
{

    class WC_Match2Pay_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {

            $this->id = 'match2pay'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Match2Pay Gateway';
            $this->method_description = 'Description of Match2Pay payment gateway'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->api_token = $this->testmode ? $this->get_option('test_api_token') : $this->get_option('api_token');
            $this->api_secret = $this->testmode ? $this->get_option('test_api_secret') : $this->get_option('api_secret');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // You can also register a callback here
            add_action('woocommerce_api_match2pay', array($this, 'callback'));

        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Match2Pay Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Cryptocurrency',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'testmode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'test_api_secret' => array(
                    'title' => 'Test Api Secret Key',
                    'type' => 'text'
                ),
                'test_api_token' => array(
                    'title' => 'Test Api Token Key',
                    'type' => 'password',
                ),
                'api_secret' => array(
                    'title' => 'Live Api Secret Key',
                    'type' => 'text'
                ),
                'api_token' => array(
                    'title' => 'Live Api Token Key',
                    'type' => 'password'
                ),
            );

        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields()
        {

            // ok, let's display some description before the payment form
            if ($this->description) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ($this->testmode) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
                    $this->description = trim($this->description);
                }
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action('woocommerce_match2pay_form_start', $this->id);

            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide"><label>Coin <span class="required">*</span></label>
                <select id="match2pay_currency" name="match2pay_currency">
                <option>USDT ERC20</option>
                <option>USDC ERC20</option>
                <option>USDT TRC20</option>
                <option>USDC TRC20</option>
                <option>USDT BEP20</option>
                <option>USDC BEP20</option>
                </select>
                </div>
                <div class="clear"></div>';

            do_action('woocommerce_match2pay_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';

        }

        /*
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts()
        {

            // we need JavaScript to process a token only on cart/checkout pages, right?
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ('no' === $this->enabled) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if (empty($this->api_token) || empty($this->api_secret)) {
                return;
            }

            // do not work with card detailes without SSL unless your website is in a test mode
            if (!$this->testmode && !is_ssl()) {
                return;
            }

            // let's suppose it is our payment processor JavaScript that allows to obtain a token
//            wp_enqueue_script('match2pay_js', 'some payment processor site/api/token.js');

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script('woocommerce_match2pay', plugins_url('/js/match2pay.js', __FILE__), array('jquery', 'match2pay_js'));

            // in most payment processors you have to use PUBLIC KEY to obtain a token
//            wp_localize_script( 'woocommerce_match2pay', 'match2pay_params', array(
//                'publishableKey' => $this->api_secret
//            ) );

            wp_enqueue_script('woocommerce_match2pay');

        }

        /*
          * Fields validation, more in Step 5
         */
        public function validate_fields()
        {

            if (empty($_POST['billing_first_name'])) {
                wc_add_notice('First name is required!', 'error');
                return false;
            }
            return true;

        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment($order_id)
        {
            $post_data = $_POST;
            // we need it to get any order detailes
            $order = wc_get_order($order_id);
            $amount = $order->get_total();
            if ($order->get_currency() !== 'USD') {
                $amount = $amount * 1.1; //TODO Конвертация в доллары, если тут будет другая валюта
            }
            $paymentGatewayName = $post_data['match2pay_currency'];
            //TODO Список поддерживаемых валют, это можно оставить тут, а лучше перенести в БД (чтоб с админки клиент мог врубать\вырубать валюты и менять лимиты). Все валюты из документации
            $currencies = [
                'BTC' => [
                    'paymentCurrency' => 'BTC',
                    'min_amount' => 0.0001
                ],
                'USDT ERC20' => [
                    'paymentCurrency' => 'UST',
                    'min_amount' => 1
                ],
                'USDC ERC20' => [
                    'paymentCurrency' => 'UCC',
                    'min_amount' => 1
                ],
                'ETH' => [
                    'paymentCurrency' => 'ETH',
                    'min_amount' => 0.0001
                ],
                'USDT BEP20' => [
                    'paymentCurrency' => 'USB',
                    'min_amount' => 0.0001
                ],
                'USDC BEP20' => [
                    'paymentCurrency' => 'USB',
                    'min_amount' => 1
                ],
                'BNB' => [
                    'paymentCurrency' => 'BNB',
                    'min_amount' => 0.1
                ],
                'USDT TRC20' => [
                    'paymentCurrency' => 'USX',
                    'min_amount' => 1
                ],
                'USDC TRC20' => [
                    'paymentCurrency' => 'UCX',
                    'min_amount' => 1
                ],
                'TRX' => [
                    'paymentCurrency' => 'TRX',
                    'min_amount' => 1
                ],
            ];
            //TODO Проверка на то есть ли у нас такая валюта
            if (empty($currencies[$paymentGatewayName])) {
                wc_add_notice('Currency error', 'error');
                return;
            }
            //TODO Проверка на мин сумму. Так же можно добавить проверку на макс сумму
            if ($amount < $currencies[$paymentGatewayName]['min_amount']) {
                wc_add_notice("Min amount {$currencies[$paymentGatewayName]['min_amount']}", 'error');
                return;
            }
            if (!strpos($amount, '.')) {
                $amount = "{$amount}.00000000";
            } elseif (strlen(explode('.', $amount)[1]) < 8) {
                $amount = $amount . str_pad('', 8 - strlen(explode('.', $amount)[1]), '0');
            }
            $api_token = $this->api_token;
            $api_secret = $this->api_secret;
            $callback_url = get_home_url() . "/index.php/wc-api/match2pay";
            $match2pay_data = [
                "amount" => $amount,
                "currency" => "USD",
                "paymentGatewayName" => $paymentGatewayName,
                "paymentCurrency" => $currencies[$paymentGatewayName]['paymentCurrency'],
                "callbackUrl" => $callback_url,
                "apiToken" => $api_token,
                "timestamp" => strtotime('now'),
            ];
            ksort($match2pay_data);
            $signature = implode($match2pay_data);
            $signature = hash('sha384', "{$signature}{$api_secret}");
            $match2pay_data['signature'] = $signature;
            if ($this->testmode) {
                $url = 'https://pp-staging.fx-edge.com/api/v2/deposit/crypto_agent';
            } else {
                $url = 'https://pp-staging.fx-edge.com/api/v2/deposit/crypto_agent'; //TODO тут подставить URL живого АПИ
            }
            try {
                $headers = [
                    'Content-Type: application/json',
                ];
                $handler = curl_init();
                curl_setopt($handler, CURLOPT_URL, $url);
                curl_setopt($handler, CURLOPT_HEADER, 0);
                curl_setopt($handler, CURLOPT_POST, true);
                curl_setopt($handler, CURLOPT_POSTFIELDS, json_encode($match2pay_data));
                curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($handler, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($handler, CURLOPT_HTTPHEADER, $headers);
                $content = curl_exec($handler);
                $result = json_decode($content, true);
                if (!empty($result['checkoutUrl'])) {
                    $order->update_meta_data('match2pay_result', json_encode($result));
                    $order->update_meta_data('match2pay_address', $result['address']);
                    $order->update_meta_data('match2pay_paymentId', $result['paymentId']);
                    $order->save();

                    // Empty cart
                    WC()->cart->empty_cart();

                    return array(
                        'result' => 'success',
                        'redirect' => $result['checkoutUrl'],
                    );
                } elseif (!empty($result['error'])) {
                    wc_add_notice($result['error'], 'error');
                    return;
                }
            } catch (\Exception $e) {
                var_dump($e);
            }
            wc_add_notice('Please try again.', 'error');
            return;
        }

        public function callback()
        {
            $webhookContent = "";
            $webhook = fopen('php://input', 'rb');
            while (!feof($webhook)) {
                $webhookContent .= fread($webhook, 4096);
            }
            fclose($webhook);
            $callback_data = json_decode($webhookContent, true);
            try {
                if ($callback_data['status'] == 'DONE') {
                    global $wpdb;
                    $paymentId = $callback_data['paymentId'];
                    $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_orders_meta WHERE meta_value = '{$paymentId}' AND meta_key = 'match2pay_paymentId'", OBJECT);
                    if (count($results) == 1 && !empty($results[0]->order_id)) {
                        $order = wc_get_order($results[0]->order_id);
                        if ($order) {
                            if ($order->get_status() == 'pending') {
                                $api_token = $this->api_token;
                                $api_secret = $this->api_secret;
                                if (!strpos($callback_data['transactionAmount'], '.')) {
                                    $callback_data['transactionAmount'] = "{$callback_data['transactionAmount']}.00000000";
                                } elseif (strlen(explode('.', $callback_data['transactionAmount'])[1]) < 8) {
                                    $callback_data['transactionAmount'] = $callback_data['transactionAmount'] . str_pad('', 8 - strlen(explode('.', $callback_data['transactionAmount'])[1]), '0');
                                }
                                $signature = hash('sha384', "{$callback_data['transactionAmount']}{$callback_data['transactionCurrency']}{$callback_data['status']}{$api_token}{$api_secret}");
                                $callback_signature = $_SERVER['HTTP_SIGNATURE'];//TODO возможно он будет не в HTTP_SIGNATURE, надо смотреть хедеры
                                if ($signature == $callback_signature) {
                                    $order_amount = $order->get_total();
                                    if ($order->get_currency() !== 'USD') {
                                        $order_amount = $order_amount * 1.1; //TODO Конвертация в доллары, если тут будет другая валюта
                                    }
                                    if ($callback_data['finalAmount'] >= $order_amount) {
                                        $order->payment_complete();
                                        $order->reduce_order_stock();
                                        $order->add_order_note('Hey, your order is paid! Thank you!', true);
                                    } else {
                                        //TODO Если сумма зашла меньше чем нужна, то сделать что-то с заказом
                                    }
                                }
                            }
                            $order->update_meta_data('match2pay_callback', json_encode($callback_data));
                        }
                    }
                }
            } catch (\Exception $e) {
                var_dump($e->getMessage());
            }
        }
    }
}
