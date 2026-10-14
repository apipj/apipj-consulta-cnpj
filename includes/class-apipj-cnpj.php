<?php
/**
 * Local CNPJ helpers: normalization, check digit validation and formatting.
 *
 * Works for the numeric CNPJ and for the alphanumeric CNPJ (IN RFB 2.229/2024):
 * positions 1-12 in [A-Z0-9], positions 13-14 (check digits) numeric; each
 * character is worth its ASCII code minus 48. Mirrors the reference
 * implementation in https://github.com/apipj/apipj-js (src/cnpj.js).
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * CNPJ helpers. No network access.
 */
final class Apipj_Cnpj {

	/**
	 * Check digit weights.
	 *
	 * @var int[]
	 */
	private static $weights = array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );

	/**
	 * Removes everything that is not a letter or a digit and uppercases.
	 *
	 * @param mixed $value Raw input, masked or not.
	 * @return string
	 */
	public static function normalize( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return strtoupper( (string) preg_replace( '/[^0-9A-Za-z]/', '', (string) $value ) );
	}

	/**
	 * Computes the two check digits of a 12 character base.
	 *
	 * @param string $base12 Twelve characters in [0-9A-Z].
	 * @return string Two digits, e.g. "35".
	 */
	public static function check_digits( $base12 ) {
		$sum1 = 0;
		$sum2 = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$value = ord( $base12[ $i ] ) - 48;
			$sum1 += $value * self::$weights[ $i + 1 ];
			$sum2 += $value * self::$weights[ $i ];
		}
		$rest1 = $sum1 % 11;
		$dv1   = $rest1 < 2 ? 0 : 11 - $rest1;
		$sum2 += $dv1 * self::$weights[12];
		$rest2 = $sum2 % 11;
		$dv2   = $rest2 < 2 ? 0 : 11 - $rest2;
		return $dv1 . $dv2;
	}

	/**
	 * Whether the input (masked or not) is a structurally valid CNPJ with correct check digits.
	 * It does not tell whether the company exists.
	 *
	 * @param mixed $value Raw input.
	 * @return bool
	 */
	public static function is_valid( $value ) {
		$cnpj = self::normalize( $value );
		if ( ! preg_match( '/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj ) || '00000000000000' === $cnpj ) {
			return false;
		}
		return substr( $cnpj, 12 ) === self::check_digits( substr( $cnpj, 0, 12 ) );
	}

	/**
	 * Formats as AA.AAA.AAA/AAAA-DD. Returns the normalized input if it does not have 14 characters.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function format( $value ) {
		$cnpj = self::normalize( $value );
		if ( 14 !== strlen( $cnpj ) ) {
			return $cnpj;
		}
		return substr( $cnpj, 0, 2 ) . '.' . substr( $cnpj, 2, 3 ) . '.' . substr( $cnpj, 5, 3 ) . '/' . substr( $cnpj, 8, 4 ) . '-' . substr( $cnpj, 12 );
	}
}
