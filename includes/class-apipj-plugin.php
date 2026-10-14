<?php
/**
 * Bootstrap.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin parts together.
 */
final class Apipj_Plugin {

	/**
	 * Instance.
	 *
	 * @var Apipj_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return Apipj_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Loads the bundled translations (pt_BR ships with the plugin; translate.wordpress.org
	 * language packs, when available, take precedence).
	 *
	 * @return void
	 */
	public function load_textdomain() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Loads the bundled pt_BR translation so Brazilian stores get Portuguese before a language pack exists.
		load_plugin_textdomain( 'apipj-consulta-cnpj', false, dirname( plugin_basename( APIPJ_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Starts the components.
	 *
	 * @return void
	 */
	public function boot() {
		Apipj_Assets::init();
		Apipj_Rest::init();
		Apipj_Forms::init();
		if ( is_admin() ) {
			Apipj_Admin::init();
		}
		if ( class_exists( 'WooCommerce' ) ) {
			Apipj_Checkout::init();
		}
	}
}
