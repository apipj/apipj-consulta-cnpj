<?php
/**
 * Script and style registration.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the front-end assets. Nothing is enqueued here: each integration enqueues what it needs.
 */
final class Apipj_Assets {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
	}

	/**
	 * Registers scripts and styles.
	 *
	 * @return void
	 */
	public static function register() {
		if ( wp_script_is( 'apipj-core', 'registered' ) ) {
			return;
		}
		$args = array( 'in_footer' => true );

		wp_register_style( 'apipj', APIPJ_PLUGIN_URL . 'assets/css/apipj.css', array(), APIPJ_VERSION );
		wp_register_script( 'apipj-core', APIPJ_PLUGIN_URL . 'assets/js/apipj-core.js', array(), APIPJ_VERSION, $args );
		wp_register_script( 'apipj-checkout-classic', APIPJ_PLUGIN_URL . 'assets/js/apipj-checkout-classic.js', array( 'apipj-core', 'jquery' ), APIPJ_VERSION, $args );
		wp_register_script( 'apipj-checkout-blocks', APIPJ_PLUGIN_URL . 'assets/js/apipj-checkout-blocks.js', array( 'apipj-core', 'wp-data' ), APIPJ_VERSION, $args );
		wp_register_script( 'apipj-forms', APIPJ_PLUGIN_URL . 'assets/js/apipj-forms.js', array( 'apipj-core' ), APIPJ_VERSION, $args );

		wp_localize_script( 'apipj-core', 'apipjConfig', self::config() );
	}

	/**
	 * Configuration passed to the scripts. Never contains the API key.
	 *
	 * @return array
	 */
	public static function config() {
		$settings = Apipj_Settings::all();
		return array(
			'restUrl'   => esc_url_raw( rest_url( Apipj_Rest::NAMESPACE_V1 . '/cnpj/' ) ),
			'token'     => Apipj_Rest::create_token(),
			'fill'      => $settings['fill'],
			'overwrite' => $settings['overwrite'],
			'inactive'  => $settings['inactive'],
			'i18n'      => array(
				'searching' => __( 'Searching…', 'apipj-consulta-cnpj' ),
				'found'     => __( 'Company found', 'apipj-consulta-cnpj' ),
				/* translators: %s: registration status at Receita Federal, e.g. BAIXADA. */
				'status'    => __( 'Status: %s', 'apipj-consulta-cnpj' ),
				'blocked'   => __( 'Orders cannot be placed with a CNPJ whose status is not ATIVA.', 'apipj-consulta-cnpj' ),
				'invalid'   => Apipj_Rest::message( 'invalid_cnpj' ),
				'notFound'  => Apipj_Rest::message( 'not_found' ),
				'failed'    => Apipj_Rest::message( 'unavailable' ),
			),
		);
	}

	/**
	 * Enqueues the shared stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue_style() {
		self::register();
		wp_enqueue_style( 'apipj' );
	}
}
