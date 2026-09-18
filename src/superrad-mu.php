<?php
/**
 * Plugin Name: Super Rad Must-Use Plugins
 * Plugin URI: https://superrad.dev/
 * Description: Super Rad-specific services and options
 * Author: ⚡️ Super Rad
 * Version: 1.0.0
 */

// Our plugin.
define( 'SRMU_PLUGIN_BASE', __FILE__ );

// Allow changing the version number in only one place (the header above).
$plugin_data = get_file_data( SRMU_PLUGIN_BASE, array( 'Version' => 'Version' ) );
define( 'SRMU_PLUGIN_VERSION', $plugin_data['Version'] );

require_once __DIR__ . '/superrad-custom-login/superrad-custom-login.php';

/**
 * Clean-up Dashboard widgets
 *
 * This function removes the default WordPress dashboard widgets.
 *
 * @return void
 */
function superrad_remove_dashboard_widgets(): void {
	remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
	remove_meta_box( 'rg_forms_dashboard', 'dashboard', 'normal' );
}

add_action( 'wp_dashboard_setup', 'superrad_remove_dashboard_widgets', 999 );

/**
 * Prevent User Enumeration
 *
 * This function parses every request and only allows the request to continue under certain conditions.
 */
function superrad_prevent_user_enumeration(): void {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}
	if ( is_admin() ) {
		return;
	}
	if ( isset( $_SERVER['REQUEST_URI'] ) && 0 !== preg_match( '#wp-comments-post#', esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['author'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! is_numeric( $_REQUEST['author'] ) ) {
		return;
	}

	// Deliberately not logged here. The Caddy access log (see
	// docker/caddy-hardening.caddyfile) already records this request with the
	// client IP, the requested author ID and the 403 status, which is strictly
	// more than an error_log() line could say. Logging it again would also hand
	// any anonymous visitor an unbounded write into the container's stderr:
	// a loop over ?author=N would grow the droplet's Docker log forever.
	if ( ! headers_sent() ) {
		header( 'HTTP/1.0 403 Forbidden' );
	}
	die;
}

add_action( 'parse_request', 'superrad_prevent_user_enumeration', 999 );


/**
 * Honor the DISABLED_PLUGINS constant.
 *
 * config/environments/*.php may define DISABLED_PLUGINS as an array of plugin
 * basenames that should not run in that environment (the starter ships
 * `cloudflare/cloudflare.php` in local.php). Nothing in WordPress or Bedrock
 * reads that constant on its own, so without this filter the list is inert
 * and the plugins keep running.
 *
 * @param mixed $plugins Active plugin basenames from the option.
 * @return array Filtered list.
 */
function superrad_filter_disabled_plugins( $plugins ): array {
	if ( ! defined( 'DISABLED_PLUGINS' ) || ! is_array( DISABLED_PLUGINS ) ) {
		return (array) $plugins;
	}

	return array_values( array_diff( (array) $plugins, DISABLED_PLUGINS ) );
}

add_filter( 'option_active_plugins', 'superrad_filter_disabled_plugins' );

/**
 * Keep disabled plugins in the stored option when something writes it.
 *
 * This is the other half of the filter above, and without it that filter is
 * destructive rather than cosmetic.
 *
 * WordPress reads active_plugins through get_option() inside
 * activate_plugin() and deactivate_plugins(), so those functions see the
 * FILTERED list, append or remove one entry, and write the result back. The
 * disabled plugins are absent from what they read, so update_option() erases
 * them from the database permanently. One `wp plugin activate` is enough.
 *
 * That is not a cosmetic bug. A site promoted from staging to production
 * loses those plugins for good: the environment stops defining
 * DISABLED_PLUGINS, but the option no longer lists them, so they never come
 * back. On a real site that means live payment, mail and lead-capture
 * integrations silently switched off while forms keep submitting.
 *
 * So re-add, before every write, any currently-disabled plugin that the
 * STORED option still lists. The stored value has to be read straight from
 * the database: get_option() would come back through the filter above and
 * report the very entries this function exists to preserve as missing.
 *
 * Deliberately narrow. It only restores entries that were already stored, so
 * it cannot activate anything, and it skips a plugin whose files are gone so
 * that deleting a disabled plugin still cleans up the option. To genuinely
 * remove one, take it out of DISABLED_PLUGINS first, then deactivate it.
 *
 * @param mixed $value     The value about to be written.
 * @param mixed $old_value The previous value, itself already filtered.
 * @return mixed Value to write.
 */
function superrad_preserve_disabled_plugins( $value, $old_value ) {
	unset( $old_value );

	if ( ! defined( 'DISABLED_PLUGINS' ) || ! is_array( DISABLED_PLUGINS ) || ! is_array( $value ) ) {
		return $value;
	}

	global $wpdb;

	$raw = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			'active_plugins'
		)
	);

	$stored = is_string( $raw ) ? maybe_unserialize( $raw ) : array();
	if ( ! is_array( $stored ) ) {
		return $value;
	}

	$restore = array();
	foreach ( array_intersect( $stored, DISABLED_PLUGINS ) as $plugin ) {
		// A disabled plugin that has been deleted from disk should fall out of
		// the option rather than be pinned there forever.
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			$restore[] = $plugin;
		}
	}

	if ( empty( $restore ) ) {
		return $value;
	}

	$merged = array_unique( array_merge( $value, $restore ) );
	sort( $merged );

	return array_values( $merged );
}

add_filter( 'pre_update_option_active_plugins', 'superrad_preserve_disabled_plugins', 10, 2 );
