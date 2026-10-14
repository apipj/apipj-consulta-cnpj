<?php
/**
 * WooCommerce integration: CNPJ field, autofill scripts and checkout validation.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classic checkout, block-based Checkout and My Account > Addresses.
 */
final class Apipj_Checkout {

	const BLOCK_FIELD = 'apipj/cnpj';

	/**
	 * Registers hooks (only called when WooCommerce is active).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'billing_fields' ), 20 );
		add_filter( 'woocommerce_process_checkout_field_billing_cnpj', array( __CLASS__, 'format_own_field' ) );
		add_filter( 'woocommerce_process_myaccount_field_billing_cnpj', array( __CLASS__, 'format_own_field' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_classic' ), 20, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_order_cnpj' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_classic' ) );
		add_action( 'woocommerce_init', array( __CLASS__, 'register_block_field' ) );
		add_action( 'woocommerce_blocks_checkout_block_registration', array( __CLASS__, 'register_block_integration' ) );
	}

	/**
	 * Whether "Brazilian Market on WooCommerce" (woocommerce-extra-checkout-fields-for-brazil) is active.
	 *
	 * @return bool
	 */
	public static function has_brazilian_market() {
		return class_exists( 'Extra_Checkout_Fields_For_Brazil' );
	}

	/**
	 * Adds a CNPJ field to the classic checkout and to My Account > Addresses, unless
	 * Brazilian Market on WooCommerce already provides one.
	 *
	 * @param array $fields Billing fields.
	 * @return array
	 */
	public static function billing_fields( $fields ) {
		if ( isset( $fields['billing_cnpj'] ) || self::has_brazilian_market() ) {
			return $fields;
		}
		/**
		 * Whether to add the billing_cnpj field to the classic checkout and My Account.
		 *
		 * @param bool $add Default true.
		 */
		if ( ! apply_filters( 'apipj_add_cnpj_field', true ) ) {
			return $fields;
		}
		$fields['billing_cnpj'] = array(
			'label'             => __( 'CNPJ', 'apipj-consulta-cnpj' ),
			'required'          => false,
			'type'              => 'text',
			'class'             => array( 'form-row-wide' ),
			'priority'          => 25,
			'autocomplete'      => 'off',
			'custom_attributes' => array(
				'maxlength'      => '18',
				'autocapitalize' => 'characters',
				'spellcheck'     => 'false',
			),
		);
		return $fields;
	}

	/**
	 * Saves the plugin's own CNPJ field with the standard mask (Brazilian Market formats its own).
	 *
	 * @param mixed $value Posted value.
	 * @return mixed
	 */
	public static function format_own_field( $value ) {
		if ( self::has_brazilian_market() || ! is_string( $value ) || ! Apipj_Cnpj::is_valid( $value ) ) {
			return $value;
		}
		return Apipj_Cnpj::format( $value );
	}

	/**
	 * Enqueues the classic checkout / My Account script.
	 *
	 * @return void
	 */
	public static function enqueue_classic() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}
		$edit_address = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' );
		$checkout     = is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' );
		if ( ! $checkout && ! $edit_address ) {
			return;
		}
		if ( $checkout && ! $edit_address && has_block( 'woocommerce/checkout' ) ) {
			return; // The block script is loaded by the Checkout block integration.
		}
		Apipj_Assets::enqueue_style();
		wp_enqueue_script( 'apipj-checkout-classic' );
	}

	/**
	 * Registers the CNPJ field of the block-based Checkout (Additional Checkout Fields API, WooCommerce 8.6+).
	 *
	 * @return void
	 */
	public static function register_block_field() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		$uses_block = true;
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) && method_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils', 'is_checkout_block_default' ) ) {
			$uses_block = \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default();
		}
		/**
		 * Whether to register the CNPJ field of the block-based Checkout.
		 * By default only when the checkout page uses the Checkout block.
		 *
		 * @param bool $register Default: whether the checkout page uses the Checkout block.
		 */
		if ( ! apply_filters( 'apipj_register_block_field', $uses_block ) ) {
			return;
		}
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => self::BLOCK_FIELD,
				'label'             => __( 'CNPJ', 'apipj-consulta-cnpj' ),
				'optionalLabel'     => __( 'CNPJ (optional)', 'apipj-consulta-cnpj' ),
				'location'          => 'contact',
				'type'              => 'text',
				'required'          => false,
				'attributes'        => array(
					'autocomplete'   => 'off',
					'autocapitalize' => 'characters',
					'maxLength'      => 18,
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize_block_field' ),
				'validate_callback' => array( __CLASS__, 'validate_block_field' ),
			)
		);
	}

	/**
	 * Sanitizes the block field: stored with the standard mask when valid.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_block_field( $value ) {
		$value = is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
		return Apipj_Cnpj::is_valid( $value ) ? Apipj_Cnpj::format( $value ) : $value;
	}

	/**
	 * Validates the block field (Store API).
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function validate_block_field( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( '' === trim( $value ) ) {
			return true;
		}
		if ( ! Apipj_Cnpj::is_valid( $value ) ) {
			return new WP_Error( 'apipj_invalid_cnpj', Apipj_Rest::message( 'invalid_cnpj' ) );
		}
		$blocked = self::inactive_message( $value );
		return '' === $blocked ? true : new WP_Error( 'apipj_inactive_cnpj', $blocked );
	}

	/**
	 * Registers the script with the Checkout block.
	 *
	 * @param object $integration_registry Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry.
	 * @return void
	 */
	public static function register_block_integration( $integration_registry ) {
		if ( ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			return;
		}
		require_once APIPJ_PLUGIN_DIR . 'includes/class-apipj-blocks-integration.php';
		$integration_registry->register( new Apipj_Blocks_Integration() );
	}

	/**
	 * Classic checkout validation.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Errors.
	 * @return void
	 */
	public static function validate_classic( $data, $errors ) {
		if ( ! is_array( $data ) || empty( $data['billing_cnpj'] ) ) {
			return;
		}
		// Brazilian Market: "1" = individual (CPF), "2" = company (CNPJ).
		if ( isset( $data['billing_persontype'] ) && '1' === (string) $data['billing_persontype'] ) {
			return;
		}
		$cnpj = (string) $data['billing_cnpj'];
		if ( ! Apipj_Cnpj::is_valid( $cnpj ) ) {
			if ( ! self::has_brazilian_market() ) { // Brazilian Market validates its own field.
				$errors->add( 'validation', Apipj_Rest::message( 'invalid_cnpj' ) );
			}
			return;
		}
		$blocked = self::inactive_message( $cnpj );
		if ( '' !== $blocked ) {
			$errors->add( 'validation', $blocked );
		}
	}

	/**
	 * When the "block checkout" option is on and the company is not ATIVA, the error message; otherwise ''.
	 * Uses the cache; API failures never block an order.
	 *
	 * @param string $cnpj CNPJ.
	 * @return string
	 */
	public static function inactive_message( $cnpj ) {
		if ( 'block' !== Apipj_Settings::get( 'inactive' ) ) {
			return '';
		}
		$company = Apipj_Client::lookup( $cnpj );
		if ( ! is_array( $company ) || Apipj_Client::is_active( $company ) ) {
			return '';
		}
		return sprintf(
			/* translators: 1: CNPJ, 2: registration status at Receita Federal (e.g. BAIXADA). */
			__( 'The CNPJ %1$s has the registration status %2$s at Receita Federal. Orders can only be placed with an active (ATIVA) CNPJ.', 'apipj-consulta-cnpj' ),
			Apipj_Cnpj::format( $cnpj ),
			$company['situacao']
		);
	}

	/**
	 * Shows the CNPJ of the classic field on the order screen (Brazilian Market shows its own).
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function admin_order_cnpj( $order ) {
		if ( self::has_brazilian_market() || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		$cnpj = $order->get_meta( '_billing_cnpj' );
		if ( $cnpj ) {
			echo '<p><strong>' . esc_html__( 'CNPJ', 'apipj-consulta-cnpj' ) . ':</strong> ' . esc_html( Apipj_Cnpj::format( $cnpj ) ) . '</p>';
		}
	}
}
