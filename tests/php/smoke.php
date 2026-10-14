<?php
/**
 * Smoke tests, run with: wp eval-file wp-content/plugins/apipj-consulta-cnpj/tests/php/smoke.php
 * Requires dev/mu-plugins/apipj-test-mocks.php (mocked BAIXADA company 11.222.333/0001-81).
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

$apipj_fail = 0;
$apipj_ok   = 0;
$apipj_t    = static function ( $name, $cond ) use ( &$apipj_fail, &$apipj_ok ) {
	if ( $cond ) {
		++$apipj_ok;
	} else {
		++$apipj_fail;
		WP_CLI::warning( 'FAIL ' . $name );
	}
};

// Check digits (numeric and alphanumeric), normalization and formatting.
$apipj_t( 'BB valid', Apipj_Cnpj::is_valid( '00.000.000/0001-91' ) );
$apipj_t( 'BB wrong DV', ! Apipj_Cnpj::is_valid( '00.000.000/0001-92' ) );
$apipj_t( 'alnum valid', Apipj_Cnpj::is_valid( '12.ABC.345/01DE-35' ) );
$apipj_t( 'alnum lowercase valid', Apipj_Cnpj::is_valid( '12.abc.345/01de-35' ) );
$apipj_t( 'alnum wrong DV', ! Apipj_Cnpj::is_valid( '12.ABC.345/01DE-36' ) );
$apipj_t( 'zeros invalid', ! Apipj_Cnpj::is_valid( '00000000000000' ) );
$apipj_t( 'letters in DV invalid', ! Apipj_Cnpj::is_valid( '12ABC34501DEAB' ) );
$apipj_t( 'short invalid', ! Apipj_Cnpj::is_valid( '1234' ) );
$apipj_t( 'normalize', '12ABC34501DE35' === Apipj_Cnpj::normalize( ' 12.abc.345/01de-35 ' ) );
$apipj_t( 'format', '12.ABC.345/01DE-35' === Apipj_Cnpj::format( '12abc34501de35' ) );
$apipj_t( 'dv calc', '35' === Apipj_Cnpj::check_digits( '12ABC34501DE' ) );

// Whitelist: partners (qsa) and anything else are dropped.
$apipj_w = Apipj_Client::whitelist(
	array(
		'nome'                => 'X SA',
		'uf'                  => 'df',
		'qsa'                 => array( array( 'nome' => 'SOCIO' ) ),
		'capital_social'      => '1.00',
		'atividade_principal' => array( array( 'code' => '64.22-1-00', 'text' => 'Bancos' ) ),
	)
);
$apipj_t( 'whitelist drops qsa', ! isset( $apipj_w['qsa'] ) && ! isset( $apipj_w['capital_social'] ) );
$apipj_t( 'whitelist uf upper', 'DF' === $apipj_w['uf'] );
$apipj_t( 'whitelist activity', '64.22-1-00 - Bancos' === $apipj_w['atividade_principal'] );

// Inactive company + "block" option (mocked API answer), cache.
delete_transient( 'apipj_cnpj_11222333000181' );
$apipj_saved = get_option( 'apipj_settings' );
update_option( 'apipj_settings', array_merge( (array) $apipj_saved, array( 'inactive' => 'block' ) ) );
$apipj_r = new ReflectionProperty( 'Apipj_Settings', 'cache' );
$apipj_r->setAccessible( true );
$apipj_r->setValue( null, null );
$apipj_msg = Apipj_Checkout::inactive_message( '11.222.333/0001-81' );
$apipj_t( 'block message', false !== strpos( $apipj_msg, 'BAIXADA' ) );
$apipj_t( 'cached without qsa', is_array( get_transient( 'apipj_cnpj_11222333000181' ) ) && ! isset( get_transient( 'apipj_cnpj_11222333000181' )['qsa'] ) );
$apipj_t( 'block field validate', is_wp_error( Apipj_Checkout::validate_block_field( '11.222.333/0001-81' ) ) );
$apipj_t( 'block field invalid DV', is_wp_error( Apipj_Checkout::validate_block_field( '11.222.333/0001-82' ) ) );
$apipj_t( 'block field empty ok', true === Apipj_Checkout::validate_block_field( '' ) );
update_option( 'apipj_settings', array_merge( (array) $apipj_saved, array( 'inactive' => 'warn' ) ) );
$apipj_r->setValue( null, null );
$apipj_t( 'warn does not block', '' === Apipj_Checkout::inactive_message( '11.222.333/0001-81' ) );
update_option( 'apipj_settings', $apipj_saved );

// Masking never reveals more than the last 4 characters.
$apipj_m = Apipj_Settings::mask_key( 'apipj_live_abcdefghijklmnop1234' );
$apipj_t( 'mask', 'apipj_live_••••••••1234' === $apipj_m );

if ( $apipj_fail ) {
	WP_CLI::error( "$apipj_fail failed, $apipj_ok passed" );
}
WP_CLI::success( "$apipj_ok assertions passed" );
