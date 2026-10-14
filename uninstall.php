<?php
/**
 * Removes the plugin options and cached lookups.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'apipj_settings' );
delete_option( 'apipj_api_key' );
delete_option( 'apipj_last_error' );

global $wpdb;

// Cached lookups and rate limit counters are transients named apipj_*.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup of this plugin's transients on uninstall; there is no API to delete transients by prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_apipj_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_apipj_' ) . '%'
	)
);
