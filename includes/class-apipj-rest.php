<?php
/**
 * Public REST proxy used by the front-end scripts.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/apipj/v1/cnpj/{cnpj}
 *
 * Public (guests use it at checkout) but protected by a nonce, local check digit
 * validation, a per-IP rate limit and a response cache. Only whitelisted fields are returned.
 */
final class Apipj_Rest {

	const NAMESPACE_V1 = 'apipj/v1';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers the route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/cnpj/(?P<cnpj>[0-9A-Za-z]{1,32})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'lookup' ),
				'permission_callback' => array( __CLASS__, 'check_nonce' ),
				'args'                => array(
					'cnpj' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( 'Apipj_Cnpj', 'normalize' ),
					),
				),
			)
		);
	}

	/**
	 * Creates the request token printed for the scripts.
	 *
	 * It works like a WordPress nonce but does not depend on the user or on the WooCommerce
	 * guest session, so it behaves the same for guests, customers and cached pages
	 * (valid for 12 to 24 hours).
	 *
	 * @param int $tick_offset 0 for the current tick, -1 for the previous one.
	 * @return string
	 */
	public static function create_token( $tick_offset = 0 ) {
		$tick = (int) ceil( time() / ( DAY_IN_SECONDS / 2 ) ) + (int) $tick_offset;
		return substr( wp_hash( $tick . '|apipj_lookup', 'nonce' ), -12, 10 );
	}

	/**
	 * Requires a valid token generated for the page that calls the route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function check_nonce( $request ) {
		$token = (string) $request->get_header( 'x_apipj_token' );
		if ( '' !== $token && ( hash_equals( self::create_token(), $token ) || hash_equals( self::create_token( -1 ), $token ) ) ) {
			return true;
		}
		return new WP_Error(
			'apipj_invalid_token',
			__( 'Your session has expired. Reload the page to look up the CNPJ.', 'apipj-consulta-cnpj' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Handles a lookup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function lookup( $request ) {
		$cnpj = Apipj_Cnpj::normalize( $request['cnpj'] );

		if ( ! Apipj_Cnpj::is_valid( $cnpj ) ) {
			return self::error( 'invalid_cnpj', 400 );
		}

		if ( ! self::within_rate_limit() ) {
			return self::error( 'too_many_requests', 429 );
		}

		$result = Apipj_Client::lookup( $cnpj );

		if ( is_wp_error( $result ) ) {
			$code   = $result->get_error_code();
			$status = array(
				'invalid_cnpj'   => 400,
				'not_found'      => 404,
				'rate_limited'   => 429,
				'quota_exceeded' => 503,
				'unauthorized'   => 503,
				'not_configured' => 503,
				'unavailable'    => 503,
			);
			return self::error( $code, isset( $status[ $code ] ) ? $status[ $code ] : 503 );
		}

		/**
		 * Filters the company data returned to the browser.
		 *
		 * @param array  $result Whitelisted company data.
		 * @param string $cnpj   Normalized CNPJ.
		 */
		$data = apply_filters( 'apipj_lookup_response', $result, $cnpj );

		$response = new WP_REST_Response(
			array(
				'found'   => true,
				'active'  => Apipj_Client::is_active( $data ),
				'company' => $data,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * Builds an error response with a shopper friendly message.
	 *
	 * @param string $code   Error code.
	 * @param int    $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function error( $code, $status ) {
		$response = new WP_REST_Response(
			array(
				'found'   => false,
				'code'    => $code,
				'message' => self::message( $code ),
			),
			$status
		);
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * Shopper friendly message for an error code. Empty means "fail silently".
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	public static function message( $code ) {
		switch ( $code ) {
			case 'invalid_cnpj':
				return __( 'Invalid CNPJ. Please check the number.', 'apipj-consulta-cnpj' );
			case 'not_found':
				return __( 'CNPJ not found in the Receita Federal database.', 'apipj-consulta-cnpj' );
			case 'too_many_requests':
				return __( 'Too many lookups in a short time. Please wait a minute or fill in the fields manually.', 'apipj-consulta-cnpj' );
			case 'rate_limited':
			case 'unavailable':
				return __( 'Could not look up the CNPJ right now. Please fill in the fields manually.', 'apipj-consulta-cnpj' );
			default:
				// quota_exceeded, unauthorized, not_configured: the store owner is notified, the shopper just types.
				return '';
		}
	}

	/**
	 * Fixed window per-IP rate limit stored in transients.
	 *
	 * @return bool False when the limit is exceeded.
	 */
	private static function within_rate_limit() {
		$limit = (int) Apipj_Settings::get( 'rate_limit' );
		if ( $limit <= 0 ) {
			return true;
		}
		$ip = self::client_ip();
		if ( '' === $ip ) {
			return true;
		}
		$key    = 'apipj_rl_' . md5( wp_salt( 'nonce' ) . $ip );
		$now    = time();
		$window = get_transient( $key );
		if ( ! is_array( $window ) || ! isset( $window['start'], $window['count'] ) || ( $now - (int) $window['start'] ) >= MINUTE_IN_SECONDS ) {
			$window = array(
				'start' => $now,
				'count' => 0,
			);
		}
		if ( (int) $window['count'] >= $limit ) {
			return false;
		}
		++$window['count'];
		set_transient( $key, $window, max( 1, MINUTE_IN_SECONDS - ( $now - (int) $window['start'] ) ) );
		return true;
	}

	/**
	 * The client IP. Uses REMOTE_ADDR only (forwarded headers can be spoofed); sites behind a
	 * trusted proxy or CDN can use the `apipj_client_ip` filter.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Filters the IP used by the per-IP rate limit.
		 *
		 * @param string $ip REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'apipj_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
