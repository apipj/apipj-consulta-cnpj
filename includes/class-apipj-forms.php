<?php
/**
 * Generic forms (Contact Form 7, Elementor, plain HTML…). Does not need WooCommerce.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the form script only where it is needed.
 */
final class Apipj_Forms {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'apipj_cnpj', array( __CLASS__, 'shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'detect' ), 999 );
		add_filter( 'widget_block_content', array( __CLASS__, 'detect' ), 999 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_everywhere' ), 20 );
	}

	/**
	 * Enqueues the form script and the status style.
	 *
	 * @return void
	 */
	public static function enqueue() {
		Apipj_Assets::enqueue_style();
		wp_enqueue_script( 'apipj-forms' );
	}

	/**
	 * [apipj_cnpj] — loads the script on the page. Outputs nothing.
	 *
	 * @return string
	 */
	public static function shortcode() {
		self::enqueue();
		return '';
	}

	/**
	 * Loads the script when the rendered content has a marked input.
	 *
	 * @param string $content HTML.
	 * @return string
	 */
	public static function detect( $content ) {
		if ( is_string( $content ) && ! is_admin() && ( false !== strpos( $content, 'apipj-cnpj' ) || false !== strpos( $content, 'apipj_cnpj' ) ) ) {
			self::enqueue();
		}
		return $content;
	}

	/**
	 * "Load on every page" option.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_everywhere() {
		if ( Apipj_Settings::get( 'load_everywhere' ) ) {
			self::enqueue();
		}
	}
}
