<?php
/**
 * Checkout block integration (loads the autofill script with the block).
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Registers the block checkout script through the WooCommerce Blocks integration API.
 */
class Apipj_Blocks_Integration implements IntegrationInterface {

	/**
	 * Integration name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'apipj-consulta-cnpj';
	}

	/**
	 * Registers scripts and styles.
	 *
	 * @return void
	 */
	public function initialize() {
		Apipj_Assets::register();
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Enqueues the status stylesheet when the Checkout block is rendered.
	 *
	 * @param string $content Block HTML.
	 * @return string
	 */
	public function enqueue_style( $content ) {
		wp_enqueue_style( 'apipj' );
		return $content;
	}

	/**
	 * Front-end script handles.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'apipj-checkout-blocks' );
	}

	/**
	 * Editor script handles (none).
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Data for the script (configuration is passed with wp_localize_script on apipj-core).
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array();
	}
}
