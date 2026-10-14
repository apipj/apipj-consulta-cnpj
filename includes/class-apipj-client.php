<?php
/**
 * API client for apipj.com.br, with a transient cache.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * Talks to https://apipj.com.br/v1. Only the CNPJ is ever sent.
 */
final class Apipj_Client {

	const BASE_URL = 'https://apipj.com.br/v1';

	/**
	 * Fields kept from the API response (everything else, such as partners, is discarded).
	 *
	 * @var string[]
	 */
	private static $whitelist = array( 'cnpj', 'nome', 'fantasia', 'situacao', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf', 'cep', 'telefone', 'email', 'atividade_principal' );

	/**
	 * Cache key for a normalized CNPJ.
	 *
	 * @param string $cnpj Normalized CNPJ.
	 * @return string
	 */
	private static function cache_key( $cnpj ) {
		return 'apipj_cnpj_' . $cnpj;
	}

	/**
	 * Looks up a CNPJ (cache first).
	 *
	 * @param string $cnpj Raw or normalized CNPJ.
	 * @return array|WP_Error Whitelisted company data, or an error with one of the codes:
	 *                        invalid_cnpj, not_found, not_configured, unauthorized, rate_limited, quota_exceeded, unavailable.
	 */
	public static function lookup( $cnpj ) {
		$cnpj = Apipj_Cnpj::normalize( $cnpj );
		if ( ! Apipj_Cnpj::is_valid( $cnpj ) ) {
			return new WP_Error( 'invalid_cnpj', 'Invalid CNPJ' );
		}

		$cached = get_transient( self::cache_key( $cnpj ) );
		if ( is_array( $cached ) ) {
			if ( ! empty( $cached['not_found'] ) ) {
				return new WP_Error( 'not_found', 'CNPJ not found' );
			}
			return $cached;
		}

		$key = Apipj_Settings::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'not_configured', 'API key not configured' );
		}

		$response = self::request( '/cnpj/' . rawurlencode( $cnpj ) . '?formato=simplificado', $key );
		if ( is_wp_error( $response ) ) {
			self::log( 'unavailable', $response->get_error_message() );
			return new WP_Error( 'unavailable', $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$code   = is_array( $body ) && isset( $body['code'] ) ? sanitize_key( $body['code'] ) : '';
		$ttl    = (int) Apipj_Settings::get( 'cache_days' ) * DAY_IN_SECONDS;

		if ( 200 === $status && is_array( $body ) ) {
			$data = self::whitelist( $body );
			if ( $ttl > 0 ) {
				set_transient( self::cache_key( $cnpj ), $data, $ttl );
			}
			return $data;
		}

		if ( 404 === $status ) {
			if ( $ttl > 0 ) {
				// Not found also counts in the quota: remember it for a day.
				set_transient( self::cache_key( $cnpj ), array( 'not_found' => true ), DAY_IN_SECONDS );
			}
			return new WP_Error( 'not_found', 'CNPJ not found' );
		}

		if ( 400 === $status ) {
			return new WP_Error( 'invalid_cnpj', 'Invalid CNPJ' );
		}

		if ( 401 === $status || 403 === $status ) {
			self::log( 'unauthorized', 'The apipj API key was rejected (HTTP ' . $status . ').' );
			return new WP_Error( 'unauthorized', 'Unauthorized' );
		}

		if ( 429 === $status ) {
			if ( 'quota_exceeded' === $code ) {
				self::log( 'quota_exceeded', 'The monthly quota of the apipj account is exhausted.' );
				return new WP_Error( 'quota_exceeded', 'Quota exceeded' );
			}
			$retry = absint( wp_remote_retrieve_header( $response, 'retry-after' ) );
			self::log( 'rate_limited', 'The per-minute limit of the apipj plan was reached.' );
			return new WP_Error( 'rate_limited', 'Rate limited', array( 'retry_after' => $retry ) );
		}

		self::log( 'unavailable', 'Unexpected response from the apipj API (HTTP ' . $status . ').' );
		return new WP_Error( 'unavailable', 'HTTP ' . $status );
	}

	/**
	 * Performs a GET request against the API.
	 *
	 * @param string $path Path relative to /v1, including the query string.
	 * @param string $key  API key, or empty for open endpoints.
	 * @return array|WP_Error
	 */
	public static function request( $path, $key = '' ) {
		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'apipj-consulta-cnpj/' . APIPJ_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
		);
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		return wp_remote_get(
			self::BASE_URL . $path,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => $headers,
			)
		);
	}

	/**
	 * Keeps only the fields needed to fill forms.
	 *
	 * @param array $body Simplified API document.
	 * @return array
	 */
	public static function whitelist( array $body ) {
		$data = array();
		foreach ( self::$whitelist as $field ) {
			if ( 'atividade_principal' === $field ) {
				$activity = '';
				if ( isset( $body[ $field ][0] ) && is_array( $body[ $field ][0] ) ) {
					$first    = $body[ $field ][0];
					$activity = trim( ( isset( $first['code'] ) ? $first['code'] . ' - ' : '' ) . ( isset( $first['text'] ) ? $first['text'] : '' ) );
				}
				$data[ $field ] = sanitize_text_field( $activity );
				continue;
			}
			$data[ $field ] = isset( $body[ $field ] ) && is_scalar( $body[ $field ] ) ? sanitize_text_field( (string) $body[ $field ] ) : '';
		}
		$data['email'] = sanitize_email( $data['email'] );
		$data['uf']    = strtoupper( substr( $data['uf'], 0, 2 ) );
		return $data;
	}

	/**
	 * Whether a looked up company is active.
	 *
	 * @param array $data Company data.
	 * @return bool
	 */
	public static function is_active( array $data ) {
		return isset( $data['situacao'] ) && 'ATIVA' === strtoupper( $data['situacao'] );
	}

	/**
	 * Logs an error for the store owner.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message (never contains the API key).
	 * @return void
	 */
	private static function log( $code, $message ) {
		if ( in_array( $code, array( 'unauthorized', 'quota_exceeded' ), true ) ) {
			Apipj_Settings::record_error( $code, $message );
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $message, array( 'source' => 'apipj-consulta-cnpj' ) );
		}
	}
}
