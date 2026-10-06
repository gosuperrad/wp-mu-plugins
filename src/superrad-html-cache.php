<?php
/**
 * Plugin Name: Super Rad HTML Cache-Control
 * Plugin URI: https://superrad.dev/
 * Description: Sends Cache-Control: no-cache on anonymous front-end HTML, so
 *              browsers revalidate a page rather than reuse it, while it stays
 *              eligible for the back/forward cache.
 * Author: ⚡️ Super Rad
 * Version: 1.0.0
 */

/**
 * Why this exists, because on a site without edge caching it looks pointless:
 *
 * - Estate sites cache anonymous HTML at Cloudflare's edge with a Cache Rule
 *   whose browser TTL is "respect origin" (the bedrock-coolify wiki,
 *   "Cloudflare caching"). WordPress sends no Cache-Control on front-end HTML,
 *   so without this header browsers fall back to the zone's default browser
 *   TTL, four hours on most zones, and keep showing a page after it has been
 *   purged at the edge.
 * - no-cache, not no-store. Both make the browser check before reusing a
 *   page, but no-store also keeps the page out of the back/forward cache.
 *   m3chiro's rule once used a browser TTL of "bypass", which Cloudflare
 *   sends as no-store, and that is how it was found.
 * - On a site with no edge caching it is harmless: the page was being
 *   fetched fresh anyway.
 * - Logged-in responses are left alone. Core sends its own no-cache headers
 *   where it matters, and the Cache Rule bypasses logged-in cookies.
 * - A header something else already set wins, including a copy of this
 *   function in a site's theme (Monarch Collective and M3 had one first).
 *
 * The superrad_html_cache_control filter changes the value, and returning an
 * empty string turns the header off for a site that needs its own.
 */

/**
 * Add Cache-Control to anonymous front-end responses.
 *
 * @param array $headers Response headers WordPress is about to send.
 * @return array The headers.
 */
function superrad_mu_html_cache_control( $headers ) {
	if ( is_user_logged_in() || isset( $headers['Cache-Control'] ) ) {
		return $headers;
	}

	$value = (string) apply_filters( 'superrad_html_cache_control', 'no-cache' );

	if ( '' !== $value ) {
		$headers['Cache-Control'] = $value;
	}

	return $headers;
}
add_filter( 'wp_headers', 'superrad_mu_html_cache_control' );
