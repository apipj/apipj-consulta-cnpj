<?php
/**
 * Plugin Name:       apipj – Consulta CNPJ for WooCommerce
 * Plugin URI:        https://github.com/apipj/apipj-consulta-cnpj
 * Description:       Autofills company name and address from a Brazilian CNPJ at the WooCommerce checkout (classic and block-based), in My Account and in any form, using the apipj CNPJ API.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            apipj
 * Author URI:        https://apipj.com.br/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       apipj-consulta-cnpj
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

define( 'APIPJ_VERSION', '1.0.0' );
define( 'APIPJ_PLUGIN_FILE', __FILE__ );
define( 'APIPJ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APIPJ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-cnpj.php';
require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-settings.php';
require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-client.php';
require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-rest.php';
require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-assets.php';
require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-admin.php';
require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-checkout.php';
require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-forms.php';
require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-plugin.php';

// Declare compatibility with WooCommerce features (HPOS and the Cart & Checkout blocks).
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

Apipj_Plugin::instance();
