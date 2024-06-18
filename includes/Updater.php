<?php

namespace Match2Pay;

class Updater {


	public $plugin_slug;
	public $version;
	public $cache_key;
	public $cache_allowed;

	public function __construct() {
		if ( defined( 'WC_MATCH2PAY_DEV_MODE' ) ) {
			add_filter( 'https_ssl_verify', '__return_false' );
			add_filter( 'https_local_ssl_verify', '__return_false' );
			add_filter( 'http_request_host_is_external', '__return_true' );
		}

		$this->plugin_slug   = dirname( plugin_basename( __DIR__ ) );
		$this->version       = WC_MATCH2PAY_VERSION;
		$this->cache_key     = 'match2pay_crypto_payments_for_woocommerce_updater';
		$this->cache_allowed = true;

		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'update' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge' ), 10, 2 );
	}

	public function request() {
		$remote = get_transient( $this->cache_key );

		if ( false === $remote || ! $this->cache_allowed ) {

			$remote = wp_remote_get(
				WC_MATCH2PAY_UPDATER_URL,
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( is_wp_error( $remote ) || 200 !== wp_remote_retrieve_response_code( $remote ) || empty( wp_remote_retrieve_body( $remote ) ) ) {
				return false;
			}

			set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );

		}

		$remote = json_decode( wp_remote_retrieve_body( $remote ) );

		return $remote;
	}

	public function info( $response, $action, $args ) {
		if ( empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $response;
		}

		if ( 'plugin_information' !== $action ) {
			return $response;
		}

		$remote = $this->request();

		if ( ! $remote ) {
			return $response;
		}

		$response = new \stdClass();

		$response->name           = $remote->name;
		$response->slug           = $remote->slug;
		$response->version        = $remote->version;
		$response->tested         = $remote->tested;
		$response->requires       = $remote->requires;
		$response->author         = $remote->author;
		$response->author_profile = $remote->author_profile;
		$response->donate_link    = $remote->donate_link;
		$response->homepage       = $remote->homepage;
		$response->download_link  = $remote->download_url;
		$response->trunk          = $remote->download_url;
		$response->requires_php   = $remote->requires_php;
		$response->last_updated   = $remote->last_updated;

		$response->sections = array(
			'description'  => $remote->sections->description,
			'installation' => $remote->sections->installation,
			'changelog'    => $remote->sections->changelog,
		);

		if ( ! empty( $remote->banners ) ) {
			$response->banners = array(
				'low'  => $remote->banners->low,
				'high' => $remote->banners->high,
			);
		}

		return $response;
	}

	public function update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->request();

		if ( $remote && version_compare( $this->version, $remote->version, '<' ) && version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' ) && version_compare( $remote->requires_php, PHP_VERSION, '<' ) ) {
			$response              = new \stdClass();
			$response->slug        = $this->plugin_slug;
			$response->plugin      = "{$this->plugin_slug}/{$this->plugin_slug}.php";
			$response->new_version = $remote->version;
			$response->tested      = $remote->tested;
			$response->package     = $remote->download_url;

			$transient->response[ $response->plugin ] = $response;

		}

		return $transient;
	}

	public function purge( $upgrader, $options ) {
		if ( $this->cache_allowed && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( $this->cache_key );
		}
	}
}
