<?php
/**
 * Settings storage: defaults, getters and sanitization.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings.
 */
final class Apipj_Settings {

	const OPTION     = 'apipj_settings';
	const KEY_OPTION = 'apipj_api_key';
	const ERR_OPTION = 'apipj_last_error';

	/**
	 * Cached settings for the current request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Fields the plugin can fill, keyed by internal name.
	 *
	 * @return array<string,string> Key => translated label.
	 */
	public static function fillable_fields() {
		return array(
			'company'      => __( 'Company name (razão social)', 'apipj-consulta-cnpj' ),
			'address'      => __( 'Street (logradouro)', 'apipj-consulta-cnpj' ),
			'number'       => __( 'Number', 'apipj-consulta-cnpj' ),
			'complement'   => __( 'Address line 2 (complemento)', 'apipj-consulta-cnpj' ),
			'neighborhood' => __( 'Neighborhood (bairro)', 'apipj-consulta-cnpj' ),
			'city'         => __( 'City', 'apipj-consulta-cnpj' ),
			'state'        => __( 'State', 'apipj-consulta-cnpj' ),
			'postcode'     => __( 'Postcode (CEP)', 'apipj-consulta-cnpj' ),
			'phone'        => __( 'Phone', 'apipj-consulta-cnpj' ),
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'fill'            => array( 'company', 'address', 'number', 'complement', 'neighborhood', 'city', 'state', 'postcode' ),
			'overwrite'       => 'empty',
			'inactive'        => 'warn',
			'rate_limit'      => 5,
			'cache_days'      => 30,
			'load_everywhere' => false,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $stored ) ? array_merge( self::defaults(), $stored ) : self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitizes a settings array.
	 *
	 * @param array $input Raw values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		$fill        = isset( $input['fill'] ) && is_array( $input['fill'] ) ? array_map( 'sanitize_key', $input['fill'] ) : array();
		$out['fill'] = array_values( array_intersect( array_keys( self::fillable_fields() ), $fill ) );

		$overwrite        = isset( $input['overwrite'] ) ? sanitize_key( $input['overwrite'] ) : '';
		$out['overwrite'] = in_array( $overwrite, array( 'empty', 'always' ), true ) ? $overwrite : $defaults['overwrite'];

		$inactive        = isset( $input['inactive'] ) ? sanitize_key( $input['inactive'] ) : '';
		$out['inactive'] = in_array( $inactive, array( 'none', 'warn', 'block' ), true ) ? $inactive : $defaults['inactive'];

		$out['rate_limit']      = isset( $input['rate_limit'] ) ? min( 1000, max( 0, absint( $input['rate_limit'] ) ) ) : $defaults['rate_limit'];
		$out['cache_days']      = isset( $input['cache_days'] ) ? min( 365, max( 0, absint( $input['cache_days'] ) ) ) : $defaults['cache_days'];
		$out['load_everywhere'] = ! empty( $input['load_everywhere'] );

		return $out;
	}

	/**
	 * Saves settings.
	 *
	 * @param array $values Raw values.
	 * @return void
	 */
	public static function update( $values ) {
		update_option( self::OPTION, self::sanitize( $values ) );
		self::$cache = null;
	}

	/**
	 * The API key. A key defined in wp-config.php (APIPJ_API_KEY) takes precedence over the stored one.
	 *
	 * @return string
	 */
	public static function api_key() {
		if ( defined( 'APIPJ_API_KEY' ) && is_string( APIPJ_API_KEY ) && '' !== APIPJ_API_KEY ) {
			return trim( APIPJ_API_KEY );
		}
		$key = get_option( self::KEY_OPTION, '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Whether the key comes from wp-config.php.
	 *
	 * @return bool
	 */
	public static function key_from_constant() {
		return defined( 'APIPJ_API_KEY' ) && is_string( APIPJ_API_KEY ) && '' !== APIPJ_API_KEY;
	}

	/**
	 * Stores a new API key (not autoloaded).
	 *
	 * @param string $key Raw key.
	 * @return void
	 */
	public static function set_api_key( $key ) {
		$key = trim( sanitize_text_field( $key ) );
		if ( '' === $key ) {
			delete_option( self::KEY_OPTION );
		} else {
			update_option( self::KEY_OPTION, $key, false );
		}
	}

	/**
	 * A masked version of a key that never reveals more than its last 4 characters.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function mask_key( $key ) {
		if ( '' === $key ) {
			return '';
		}
		$prefix = 0 === strpos( $key, 'apipj_live_' ) ? 'apipj_live_' : '';
		return $prefix . str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	/**
	 * Records the last relevant API error for the store owner (admin notice).
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return void
	 */
	public static function record_error( $code, $message ) {
		$current = get_option( self::ERR_OPTION );
		if ( is_array( $current ) && isset( $current['code'] ) && $current['code'] === $code && ( time() - (int) $current['time'] ) < 300 ) {
			return; // Avoid a database write on every failing request.
		}
		update_option(
			self::ERR_OPTION,
			array(
				'code'    => sanitize_key( $code ),
				'message' => sanitize_text_field( $message ),
				'time'    => time(),
			),
			false
		);
	}

	/**
	 * Clears the recorded error.
	 *
	 * @return void
	 */
	public static function clear_error() {
		delete_option( self::ERR_OPTION );
	}

	/**
	 * Capability required to manage the plugin.
	 *
	 * @return string
	 */
	public static function capability() {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * URL of the settings screen.
	 *
	 * @return string
	 */
	public static function page_url() {
		if ( class_exists( 'WooCommerce' ) ) {
			return admin_url( 'admin.php?page=wc-settings&tab=apipj' );
		}
		return admin_url( 'options-general.php?page=apipj' );
	}
}
