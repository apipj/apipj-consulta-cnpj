<?php
/**
 * Settings screen, connection test and admin notices.
 *
 * @package Apipj_Consulta_Cnpj
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin side of the plugin.
 */
final class Apipj_Admin {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'wc_tab' ), 60 );
		add_action( 'woocommerce_settings_apipj', array( __CLASS__, 'render_wc_tab' ) );
		add_action( 'woocommerce_settings_save_apipj', array( __CLASS__, 'save_wc_tab' ) );
		add_action( 'admin_post_apipj_save_settings', array( __CLASS__, 'save_standalone' ) );
		add_action( 'admin_post_apipj_dismiss_error', array( __CLASS__, 'dismiss_error' ) );
		add_action( 'wp_ajax_apipj_test_connection', array( __CLASS__, 'test_connection' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( APIPJ_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Settings > apipj when WooCommerce is not active.
	 *
	 * @return void
	 */
	public static function menu() {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_options_page(
			__( 'apipj – CNPJ lookup', 'apipj-consulta-cnpj' ),
			'apipj',
			'manage_options',
			'apipj',
			array( __CLASS__, 'render_standalone' )
		);
	}

	/**
	 * Adds the WooCommerce > Settings > apipj tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function wc_tab( $tabs ) {
		$tabs['apipj'] = 'apipj';
		return $tabs;
	}

	/**
	 * Whether the current screen is the plugin settings screen.
	 *
	 * @return bool
	 */
	private static function is_settings_screen() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable
		return ( 'wc-settings' === $page && 'apipj' === $tab ) || 'apipj' === $page;
	}

	/**
	 * Enqueues the settings screen script.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::is_settings_screen() ) {
			return;
		}
		wp_enqueue_script( 'apipj-admin', APIPJ_PLUGIN_URL . 'assets/js/apipj-admin.js', array(), APIPJ_VERSION, array( 'in_footer' => true ) );
		wp_localize_script(
			'apipj-admin',
			'apipjAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'apipj_test_connection' ),
				'i18n'    => array(
					'testing' => __( 'Testing…', 'apipj-consulta-cnpj' ),
					'failed'  => __( 'The test could not be completed.', 'apipj-consulta-cnpj' ),
				),
			)
		);
		wp_add_inline_style( 'common', '.apipj-test-result{display:block;margin-top:8px}.apipj-test-result.apipj-ok{color:#00703c}.apipj-test-result.apipj-fail{color:#b32d2e}.apipj-key-current{font-family:monospace}' );
	}

	/**
	 * Renders the tab inside WooCommerce settings (the form, nonce and save button are WooCommerce's).
	 *
	 * @return void
	 */
	public static function render_wc_tab() {
		echo '<h2>' . esc_html__( 'apipj – CNPJ lookup', 'apipj-consulta-cnpj' ) . '</h2>';
		self::render_fields();
	}

	/**
	 * Saves the WooCommerce tab. WooCommerce already verified its nonce; it is checked again here.
	 *
	 * @return void
	 */
	public static function save_wc_tab() {
		check_admin_referer( 'woocommerce-settings' );
		if ( ! current_user_can( Apipj_Settings::capability() ) ) {
			return;
		}
		self::save_from_request();
	}

	/**
	 * Renders Settings > apipj.
	 *
	 * @return void
	 */
	public static function render_standalone() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'apipj – CNPJ lookup', 'apipj-consulta-cnpj' ) . '</h1>';
		// The "Settings saved." notice is printed by WordPress (options-head.php) for ?updated=1.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="apipj_save_settings" />';
		wp_nonce_field( 'apipj_save_settings' );
		self::render_fields();
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Saves Settings > apipj.
	 *
	 * @return void
	 */
	public static function save_standalone() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'apipj-consulta-cnpj' ), 403 );
		}
		check_admin_referer( 'apipj_save_settings' );
		self::save_from_request();
		wp_safe_redirect( add_query_arg( 'updated', '1', Apipj_Settings::page_url() ) );
		exit;
	}

	/**
	 * Saves the posted settings. Callers verify the nonce and the capability.
	 *
	 * @return void
	 */
	private static function save_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by the callers.
		$raw = isset( $_POST['apipj'] ) && is_array( $_POST['apipj'] ) ? map_deep( wp_unslash( $_POST['apipj'] ), 'sanitize_text_field' ) : array();
		Apipj_Settings::update( $raw );

		if ( ! Apipj_Settings::key_from_constant() ) {
			if ( ! empty( $_POST['apipj_remove_key'] ) ) {
				Apipj_Settings::set_api_key( '' );
				Apipj_Settings::clear_error();
			} elseif ( ! empty( $_POST['apipj_api_key'] ) ) {
				Apipj_Settings::set_api_key( sanitize_text_field( wp_unslash( $_POST['apipj_api_key'] ) ) );
				Apipj_Settings::clear_error();
			}
		}
		// phpcs:enable
	}

	/**
	 * Renders the settings fields.
	 *
	 * @return void
	 */
	private static function render_fields() {
		$s   = Apipj_Settings::all();
		$key = Apipj_Settings::api_key();
		?>
		<p><?php esc_html_e( 'Fills company name and address from the CNPJ typed by the customer, using data from the Receita Federal open data files served by the apipj API. Only the CNPJ is sent to apipj.com.br.', 'apipj-consulta-cnpj' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="apipj_api_key"><?php esc_html_e( 'API key', 'apipj-consulta-cnpj' ); ?></label></th>
				<td>
					<?php if ( Apipj_Settings::key_from_constant() ) : ?>
						<p><span class="apipj-key-current"><?php echo esc_html( Apipj_Settings::mask_key( $key ) ); ?></span> &mdash; <?php esc_html_e( 'defined by the APIPJ_API_KEY constant in wp-config.php.', 'apipj-consulta-cnpj' ); ?></p>
					<?php else : ?>
						<?php if ( '' !== $key ) : ?>
							<p><?php esc_html_e( 'Saved key:', 'apipj-consulta-cnpj' ); ?> <span class="apipj-key-current"><?php echo esc_html( Apipj_Settings::mask_key( $key ) ); ?></span></p>
						<?php endif; ?>
						<input type="password" class="regular-text" id="apipj_api_key" name="apipj_api_key" value="" autocomplete="new-password" spellcheck="false" placeholder="<?php echo esc_attr( '' !== $key ? __( 'Paste a new key to replace the saved one', 'apipj-consulta-cnpj' ) : 'apipj_live_...' ); ?>" />
						<?php if ( '' !== $key ) : ?>
							<p><label><input type="checkbox" name="apipj_remove_key" value="1" /> <?php esc_html_e( 'Remove the saved key', 'apipj-consulta-cnpj' ); ?></label></p>
						<?php endif; ?>
						<p class="description">
							<?php
							printf(
								wp_kses(
										/* translators: 1: sign-up URL, 2: pricing URL. */
									__( 'No key yet? <a href="%1$s" target="_blank" rel="noopener noreferrer">Create a free account</a> (10 lookups per minute, 50,000 per month, no credit card). Need more? See the <a href="%2$s" target="_blank" rel="noopener noreferrer">plans</a>.', 'apipj-consulta-cnpj' ),
									array(
										'a' => array(
											'href'   => array(),
											'target' => array(),
											'rel'    => array(),
										),
									)
								),
								esc_url( 'https://apipj.com.br/criar-conta' ),
								esc_url( 'https://apipj.com.br/precos' )
							);
							?>
							<br /><?php esc_html_e( 'The key is stored in the database and never shown again in full nor sent to the browser. You can also define it in wp-config.php: define( \'APIPJ_API_KEY\', \'apipj_live_...\' );', 'apipj-consulta-cnpj' ); ?>
						</p>
					<?php endif; ?>
					<p>
						<button type="button" class="button" id="apipj-test-connection"><?php esc_html_e( 'Test connection', 'apipj-consulta-cnpj' ); ?></button>
						<span class="apipj-test-result" id="apipj-test-result" role="status" aria-live="polite"></span>
					</p>
					<p class="description"><?php esc_html_e( 'Checks that the API is online and that the key works by looking up one CNPJ (uses 1 query of your quota).', 'apipj-consulta-cnpj' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fields to fill', 'apipj-consulta-cnpj' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Fields to fill', 'apipj-consulta-cnpj' ); ?></legend>
						<?php foreach ( Apipj_Settings::fillable_fields() as $field => $label ) : ?>
							<label style="display:block;margin-bottom:4px"><input type="checkbox" name="apipj[fill][]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, $s['fill'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'If the form has no Number or Neighborhood field, the number is appended to the street and the neighborhood to address line 2.', 'apipj-consulta-cnpj' ); ?></p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Existing values', 'apipj-consulta-cnpj' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Existing values', 'apipj-consulta-cnpj' ); ?></legend>
						<label style="display:block;margin-bottom:4px"><input type="radio" name="apipj[overwrite]" value="empty" <?php checked( 'empty', $s['overwrite'] ); ?> /> <?php esc_html_e( 'Only fill empty fields (recommended)', 'apipj-consulta-cnpj' ); ?></label>
						<label style="display:block"><input type="radio" name="apipj[overwrite]" value="always" <?php checked( 'always', $s['overwrite'] ); ?> /> <?php esc_html_e( 'Always replace with the data from the CNPJ', 'apipj-consulta-cnpj' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="apipj_inactive"><?php esc_html_e( 'Inactive companies', 'apipj-consulta-cnpj' ); ?></label></th>
				<td>
					<select id="apipj_inactive" name="apipj[inactive]">
						<option value="none" <?php selected( 'none', $s['inactive'] ); ?>><?php esc_html_e( 'Do nothing', 'apipj-consulta-cnpj' ); ?></option>
						<option value="warn" <?php selected( 'warn', $s['inactive'] ); ?>><?php esc_html_e( 'Show the registration status to the customer', 'apipj-consulta-cnpj' ); ?></option>
						<option value="block" <?php selected( 'block', $s['inactive'] ); ?>><?php esc_html_e( 'Block checkout', 'apipj-consulta-cnpj' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'What to do when the registration status (situação cadastral) is not ATIVA, e.g. BAIXADA, INAPTA or SUSPENSA.', 'apipj-consulta-cnpj' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="apipj_rate_limit"><?php esc_html_e( 'Lookups per visitor', 'apipj-consulta-cnpj' ); ?></label></th>
				<td>
					<input type="number" min="0" max="1000" step="1" class="small-text" style="width:6em" id="apipj_rate_limit" name="apipj[rate_limit]" value="<?php echo esc_attr( (string) $s['rate_limit'] ); ?>" /> <?php esc_html_e( 'per minute, per IP address', 'apipj-consulta-cnpj' ); ?>
					<p class="description"><?php esc_html_e( 'Protects your quota against abuse. 0 disables the limit.', 'apipj-consulta-cnpj' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="apipj_cache_days"><?php esc_html_e( 'Cache', 'apipj-consulta-cnpj' ); ?></label></th>
				<td>
					<input type="number" min="0" max="365" step="1" class="small-text" style="width:6em" id="apipj_cache_days" name="apipj[cache_days]" value="<?php echo esc_attr( (string) $s['cache_days'] ); ?>" /> <?php esc_html_e( 'days', 'apipj-consulta-cnpj' ); ?>
					<p class="description"><?php esc_html_e( 'Repeated lookups of the same CNPJ are answered from the cache and do not use your quota. The Receita Federal data is updated monthly.', 'apipj-consulta-cnpj' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Other forms', 'apipj-consulta-cnpj' ); ?></th>
				<td>
					<label><input type="checkbox" name="apipj[load_everywhere]" value="1" <?php checked( $s['load_everywhere'] ); ?> /> <?php esc_html_e( 'Load the form script on every page', 'apipj-consulta-cnpj' ); ?></label>
					<p class="description">
						<?php esc_html_e( 'Any form works: add data-apipj-cnpj (or the CSS class apipj-cnpj) to the CNPJ input and data-apipj-campo="nome", "cep", "logradouro", "numero", "bairro", "municipio", "uf", "telefone", "email"… (or the class apipj-campo-nome etc.) to the inputs to fill. Inputs named or with the ID apipj_cnpj, apipj_nome, apipj_cep… also work (Contact Form 7, Elementor). The script loads automatically when the page content contains these markers or the [apipj_cnpj] shortcode; enable this option for forms in widgets, popups or templates.', 'apipj-consulta-cnpj' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * AJAX: tests the API status and the key.
	 *
	 * @return void
	 */
	public static function test_connection() {
		check_ajax_referer( 'apipj_test_connection' );
		if ( ! current_user_can( Apipj_Settings::capability() ) ) {
			wp_send_json_error( array( 'lines' => array( __( 'You are not allowed to do this.', 'apipj-consulta-cnpj' ) ) ), 403 );
		}

		$typed = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
		$key   = '' !== $typed ? $typed : Apipj_Settings::api_key();
		$lines = array();

		$status = Apipj_Client::request( '/status' );
		$body   = is_wp_error( $status ) ? null : json_decode( wp_remote_retrieve_body( $status ), true );
		if ( is_array( $body ) && isset( $body['status'] ) && 200 === (int) wp_remote_retrieve_response_code( $status ) ) {
			/* translators: 1: API status (OK/DEGRADED), 2: month of the Receita Federal data (YYYY-MM). */
			$lines[] = sprintf( __( 'API reachable (status %1$s, data from %2$s).', 'apipj-consulta-cnpj' ), sanitize_text_field( (string) $body['status'] ), isset( $body['dataset_month'] ) ? sanitize_text_field( (string) $body['dataset_month'] ) : '?' );
		} else {
			$lines[] = is_wp_error( $status )
				/* translators: %s: error message. */
				? sprintf( __( 'Could not reach apipj.com.br: %s', 'apipj-consulta-cnpj' ), $status->get_error_message() )
				: __( 'Could not reach apipj.com.br.', 'apipj-consulta-cnpj' );
			wp_send_json_error( array( 'lines' => $lines ) );
		}

		if ( '' === $key ) {
			$lines[] = __( 'No API key saved yet.', 'apipj-consulta-cnpj' );
			wp_send_json_error( array( 'lines' => $lines ) );
		}

		$response = Apipj_Client::request( '/cnpj/00000000000191?formato=simplificado', $key );
		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message. */
			$lines[] = sprintf( __( 'Could not reach apipj.com.br: %s', 'apipj-consulta-cnpj' ), $response->get_error_message() );
			wp_send_json_error( array( 'lines' => $lines ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$err  = is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : '';

		if ( 200 === $code ) {
			$remaining = wp_remote_retrieve_header( $response, 'x-quota-remaining' );
			$limit     = wp_remote_retrieve_header( $response, 'x-quota-limit' );
			$lines[]   = __( 'API key accepted.', 'apipj-consulta-cnpj' );
			if ( '' !== $remaining && '' !== $limit ) {
				/* translators: 1: remaining lookups, 2: monthly quota. */
				$lines[] = sprintf( __( 'Monthly quota: %1$s of %2$s lookups left.', 'apipj-consulta-cnpj' ), number_format_i18n( (float) $remaining ), number_format_i18n( (float) $limit ) );
			}
			if ( '' === $typed ) {
				Apipj_Settings::clear_error();
			} else {
				$lines[] = __( 'Remember to save the settings to keep this key.', 'apipj-consulta-cnpj' );
			}
			wp_send_json_success( array( 'lines' => $lines ) );
		}

		if ( 401 === $code || 403 === $code ) {
			$lines[] = __( 'The API key was rejected. Check that it was copied in full (it starts with apipj_live_) and that it was not revoked in your apipj dashboard.', 'apipj-consulta-cnpj' );
		} elseif ( 429 === $code && 'quota_exceeded' === $err ) {
			$lines[] = __( 'The monthly quota of this account is exhausted. It resets on the 1st of the month; upgrade at apipj.com.br/precos.', 'apipj-consulta-cnpj' );
		} elseif ( 429 === $code ) {
			$lines[] = __( 'The API key works, but the per-minute limit was reached. Try again in a minute.', 'apipj-consulta-cnpj' );
		} else {
			/* translators: %d: HTTP status code. */
			$lines[] = sprintf( __( 'Unexpected answer from the API (HTTP %d). Try again later.', 'apipj-consulta-cnpj' ), $code );
		}
		wp_send_json_error( array( 'lines' => $lines ) );
	}

	/**
	 * Admin notices: API errors (rejected key, exhausted quota) and missing key.
	 *
	 * @return void
	 */
	public static function notices() {
		if ( ! current_user_can( Apipj_Settings::capability() ) ) {
			return;
		}
		$error = get_option( Apipj_Settings::ERR_OPTION );
		if ( is_array( $error ) && ! empty( $error['code'] ) ) {
			if ( 'quota_exceeded' === $error['code'] ) {
				$text = __( 'apipj – Consulta CNPJ: the monthly lookup quota of your apipj account is exhausted, so CNPJ autofill is paused (customers can still type their data). The quota resets on the 1st of the month, or you can upgrade your plan.', 'apipj-consulta-cnpj' );
				$link = array( 'https://apipj.com.br/precos', __( 'See plans', 'apipj-consulta-cnpj' ) );
			} else {
				$text = __( 'apipj – Consulta CNPJ: the API key was rejected, so CNPJ autofill is paused. Check the key in the settings.', 'apipj-consulta-cnpj' );
				$link = array( Apipj_Settings::page_url(), __( 'Settings', 'apipj-consulta-cnpj' ) );
			}
			$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=apipj_dismiss_error' ), 'apipj_dismiss_error' );
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a> | <a href="%4$s">%5$s</a></p></div>',
				esc_html( $text ),
				esc_url( $link[0] ),
				esc_html( $link[1] ),
				esc_url( $dismiss ),
				esc_html__( 'Dismiss', 'apipj-consulta-cnpj' )
			);
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'plugins' === $screen->id && '' === Apipj_Settings::api_key() ) {
			printf(
				'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'apipj – Consulta CNPJ needs an API key to look up CNPJs.', 'apipj-consulta-cnpj' ),
				esc_url( Apipj_Settings::page_url() ),
				esc_html__( 'Add your key', 'apipj-consulta-cnpj' )
			);
		}
	}

	/**
	 * Dismisses the API error notice.
	 *
	 * @return void
	 */
	public static function dismiss_error() {
		if ( ! current_user_can( Apipj_Settings::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'apipj-consulta-cnpj' ), 403 );
		}
		check_admin_referer( 'apipj_dismiss_error' );
		Apipj_Settings::clear_error();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( Apipj_Settings::page_url() ) . '">' . esc_html__( 'Settings', 'apipj-consulta-cnpj' ) . '</a>' );
		return $links;
	}
}
