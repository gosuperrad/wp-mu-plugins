<?php
/**
 * Plugin Name: Super Rad Mail Guard
 * Plugin URI: https://superrad.dev/
 * Description: Redirects every outbound email to a single safe recipient on
 *              non-production environments, so a staging site can exercise
 *              real mail delivery without any of it reaching real people.
 * Author: ⚡️ Super Rad
 * Version: 1.0.1
 */

/**
 * Notes on scope, because the obvious implementation is not enough:
 *
 * - The guard is active only when MAIL_REDIRECT_TO is set, rather than
 *   keying off WP_ENV. That is deliberate. DDEV already routes all mail to
 *   Mailpit locally, so there is nothing to guard against there and
 *   rewriting recipients would only make Mailpit harder to read. Staging
 *   sets the var in Coolify; production never sets it and this file is inert.
 *
 * - Two filters are needed, not one. The `mailgun` plugin overrides wp_mail(),
 *   so filtering `wp_mail` covers it and all WordPress core mail. But the
 *   Gravity Forms Mailgun add-on (gravityformsmailgun) sends through the
 *   Mailgun API directly and never touches wp_mail(), so notifications would
 *   sail straight past a wp_mail-only guard. `gform_notification` catches
 *   those regardless of which GF sending service is active. Any site running
 *   both plugins springs this trap, and the failure is silent: mail appears
 *   to be guarded right up until a form notification reaches a real client.
 *
 * - wp_mail() accepts $headers as EITHER an array of lines OR a single
 *   newline-separated string. Casting the string form to an array yields one
 *   element holding every header at once, so a per-element Cc/Bcc match only
 *   ever tests the first line and every later Cc/Bcc sails through. That is a
 *   silent leak to exactly the real recipients this file exists to protect,
 *   so headers are normalised to one line per element before filtering.
 *
 * - Recipients are treated as untrusted. A Gravity Forms notification can be
 *   addressed from a form field, which puts an attacker-controlled string
 *   into $original. Embedding that in a header or a subject unescaped is
 *   textbook header injection: a CRLF re-opens the header block and can add
 *   a Bcc. So it is flattened to a single line before use.
 */

/**
 * The address every outbound message is rerouted to, or null when the guard
 * is inactive.
 */
function superrad_mail_redirect_to(): ?string {
	if ( ! defined( 'MAIL_REDIRECT_TO' ) ) {
		return null;
	}

	$address = trim( (string) MAIL_REDIRECT_TO );

	if ( '' === $address || ! is_email( $address ) ) {
		return null;
	}

	return $address;
}

/**
 * Flatten wp_mail()'s $to, which may be an array or a comma-separated string.
 *
 * @param mixed $to Recipient(s) in either accepted form.
 * @return string Comma-separated list for display.
 */
function superrad_mail_flatten_recipients( $to ): string {
	if ( is_array( $to ) ) {
		return implode( ', ', array_map( 'strval', $to ) );
	}

	return (string) $to;
}

/**
 * Make a value safe to embed in a header or a subject line.
 *
 * Collapses CR/LF to spaces so a hostile recipient string cannot re-open the
 * header block, and caps the length so a long recipient list does not produce
 * an unreadable subject.
 *
 * @param string $value Untrusted value.
 * @return string Single-line, length-capped value.
 */
function superrad_mail_header_safe( string $value ): string {
	$value = (string) preg_replace( '/[\r\n\t]+/', ' ', $value );
	$value = trim( (string) preg_replace( '/ {2,}/', ' ', $value ) );

	if ( mb_strlen( $value ) > 160 ) {
		$value = mb_substr( $value, 0, 157 ) . '...';
	}

	return $value;
}

/**
 * Normalise wp_mail()'s $headers to one header per array element.
 *
 * Accepts the array form and the newline-separated string form, and also
 * splits any array element that itself holds several lines.
 *
 * @param mixed $headers Headers in either accepted form.
 * @return array<int, string> One header per element.
 */
function superrad_mail_normalize_headers( $headers ): array {
	$lines = array();

	foreach ( (array) $headers as $chunk ) {
		foreach ( preg_split( "/\r\n|\r|\n/", (string) $chunk ) as $line ) {
			if ( '' !== trim( $line ) ) {
				$lines[] = $line;
			}
		}
	}

	return $lines;
}

/**
 * Reroute WordPress core mail, and anything routed through wp_mail() such as
 * the Mailgun plugin.
 *
 * @param array $args wp_mail() arguments.
 * @return array Filtered arguments.
 */
function superrad_mail_guard_wp_mail( $args ): array {
	$address = superrad_mail_redirect_to();

	if ( null === $address ) {
		return $args;
	}

	$original = superrad_mail_header_safe(
		superrad_mail_flatten_recipients( $args['to'] ?? '' )
	);

	$args['to'] = $address;

	// Drop any cc/bcc rather than rewriting them; a single copy is enough and
	// leaving them would send to real people through the back door.
	$headers = array_values(
		array_filter(
			superrad_mail_normalize_headers( $args['headers'] ?? array() ),
			function ( $header ) {
				return ! preg_match( '/^\s*(cc|bcc)\s*:/i', $header );
			}
		)
	);

	if ( '' !== $original ) {
		$headers[]       = 'X-Original-To: ' . $original;
		$args['subject'] = '[staging -> ' . $original . '] ' . superrad_mail_header_safe( (string) ( $args['subject'] ?? '' ) );
	}

	$args['headers'] = $headers;

	return $args;
}
add_filter( 'wp_mail', 'superrad_mail_guard_wp_mail', 999 );

/**
 * Reroute Gravity Forms notifications.
 *
 * Runs regardless of which GF sending add-on is active, which is the case
 * wp_mail filtering alone does not cover.
 *
 * This sets the recipient and the subject prefix only. Gravity Forms builds
 * its own headers downstream, so X-Original-To is added by the wp_mail filter
 * above when a notification goes out that way. The subject prefix is what is
 * guaranteed on both paths, including the Mailgun add-on's direct API send.
 *
 * @param array $notification The notification about to be sent.
 * @param array $form         The form it belongs to.
 * @param array $entry        The entry that triggered it.
 * @return array Filtered notification.
 */
function superrad_mail_guard_gform_notification( $notification, $form = array(), $entry = array() ): array {
	$address = superrad_mail_redirect_to();

	if ( null === $address ) {
		return $notification;
	}

	$original = superrad_mail_header_safe(
		superrad_mail_flatten_recipients( $notification['to'] ?? '' )
	);

	$notification['to']     = $address;
	$notification['toType'] = 'email';

	// Same reasoning as above: these would otherwise reach real recipients.
	unset( $notification['cc'], $notification['bcc'] );

	if ( '' !== $original ) {
		$notification['subject'] = '[staging -> ' . $original . '] ' . superrad_mail_header_safe( (string) ( $notification['subject'] ?? '' ) );
	}

	return $notification;
}
add_filter( 'gform_notification', 'superrad_mail_guard_gform_notification', 999, 3 );
