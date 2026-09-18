<?php
/**
 * Plugin Name: Super Rad Custom Login
 * Plugin URI: https://gitlab.com/superrad/plugins/superrad-custom-login
 * Description: Custom login styles for Super Rad.
 * Author: ⚡️Super Rad
 * Author URI: https://superrad.dev
 * Text Domain: superrad-custom-login
 * Version: 2.0.0
 * Update URI: https://gitlab.com/superrad/plugins/superrad-custom-login
 * Requires PHP: 8.0
 * Requires at least: 5.7
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace SUPERRAD\CustomLogin;

define( 'SRCL_PLUGIN_BASE', __FILE__ );
define( 'SRCL_PLUGIN_URL', plugins_url( '', __FILE__ ) );

$plugin_data = get_file_data( SRCL_PLUGIN_BASE, array( 'Version' => 'Version' ) );
define( 'SRCL_PLUGIN_VERSION', $plugin_data['Version'] );

/**
 * Enqueue custom login stylesheet
 */
function enqueue_custom_login_styles(): void {
//    wp_deregister_style('login');
	wp_enqueue_style(
		'superrad-custom-login',
		SRCL_PLUGIN_URL . '/assets/styles.css',
		array(),
		SRCL_PLUGIN_VERSION
	);
}

add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\enqueue_custom_login_styles' );

/**
 * Replace WordPress in <title>
 *
 * @param $origtitle
 *
 * @return string
 */
function override_login_title( $origtitle ): string {
	$blog_name = get_option( 'blogname' );

	return esc_html( 'Login &lsaquo; ' ) . $blog_name;
}

add_filter( 'login_title', __NAMESPACE__ . '\override_login_title', 99 );

/**
 * Set logo url to home
 * @return string
 */
function set_login_logo_url(): string {
	return esc_url( home_url() );
}

add_filter( 'login_headerurl', __NAMESPACE__ . '\set_login_logo_url' );

/**
 * Change Header Title to blogname
 * @return string
 */
function set_login_header_title(): string {
	$blog_name = get_option( 'blogname' );

	return $blog_name;
}

add_filter( 'login_headertext', __NAMESPACE__ . '\set_login_header_title' );
