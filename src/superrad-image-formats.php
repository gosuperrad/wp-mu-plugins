<?php
/**
 * Plugin Name: Super Rad Image Output Format
 * Plugin URI: https://superrad.dev/
 * Description: Serves uploaded raster images as WebP (or AVIF) rather than
 *              as same-format copies, so everything in the front-end srcset
 *              is modern-format regardless of what the client uploaded. The
 *              uploaded source file is kept on disk untouched.
 * Author: ⚡️ Super Rad
 * Version: 1.0.0
 */

/**
 * Notes on scope, because this is easy to misread:
 *
 * - Verified behavior on WP 7.1: both the generated sub-sizes AND the
 *   attachment's primary file become WebP, while the uploaded source file
 *   stays on disk untouched next to them (photo.jpg and photo.webp both
 *   exist; wp_get_attachment_metadata()['file'] points at the .webp). So
 *   nothing is destructive, and disabling this later is just a
 *   `wp media regenerate` away. It does mean roughly one extra stored copy
 *   per upload, which matters for the R2 bill but not for correctness.
 * - PNG conversion is OFF by default. Lossy WebP handles alpha fine, but it
 *   does put artifacts on the hard edges of logos and flat-color graphics,
 *   and this template gets applied across a client estate without a
 *   per-site visual review. Set WP_IMAGE_CONVERT_PNG=true for a site whose
 *   PNGs are photographs rather than graphics.
 * - The production image has Imagick with WEBP and AVIF delegates. DDEV's
 *   web container may not, which is why every filter here checks
 *   wp_image_editor_supports() first rather than assuming.
 */

/**
 * Target output mime type, or null when conversion is disabled/unsupported.
 */
function superrad_image_output_format(): ?string {
	$format = defined( 'WP_IMAGE_OUTPUT_FORMAT' ) ? strtolower( (string) WP_IMAGE_OUTPUT_FORMAT ) : 'webp';

	if ( ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
		return null;
	}

	$mime = 'image/' . $format;

	// If the active editor can't write this format, returning it would make
	// WordPress fall back silently and produce same-format sub-sizes. Bail
	// explicitly instead so behavior is the same, but knowable.
	if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
		return null;
	}

	return $mime;
}

/**
 * Map uploaded source formats onto the modern output format.
 *
 * @param array $formats Existing mime-type map.
 * @return array
 */
function superrad_image_editor_output_format( $formats ): array {
	$target = superrad_image_output_format();

	if ( null === $target ) {
		return (array) $formats;
	}

	$formats = (array) $formats;

	$formats['image/jpeg'] = $target;

	if ( defined( 'WP_IMAGE_CONVERT_PNG' ) && WP_IMAGE_CONVERT_PNG ) {
		$formats['image/png'] = $target;
	}

	return $formats;
}

add_filter( 'image_editor_output_format', 'superrad_image_editor_output_format' );

/**
 * Compression quality for the generated sub-sizes.
 *
 * WordPress core defaults to 82 for every format. WebP and AVIF both hold up
 * noticeably better than JPEG at the same number, so the useful knob here is
 * per-site tuning rather than a different global default.
 *
 * @param int    $quality Existing quality.
 * @param string $mime    Mime type being written.
 * @return int
 */
function superrad_image_editor_quality( $quality, $mime ): int {
	if ( ! in_array( $mime, array( 'image/webp', 'image/avif' ), true ) ) {
		return (int) $quality;
	}

	if ( ! defined( 'WP_IMAGE_OUTPUT_QUALITY' ) || ! WP_IMAGE_OUTPUT_QUALITY ) {
		return (int) $quality;
	}

	return max( 1, min( 100, (int) WP_IMAGE_OUTPUT_QUALITY ) );
}

add_filter( 'wp_editor_set_quality', 'superrad_image_editor_quality', 10, 2 );

/**
 * Allow WebP and AVIF to be uploaded directly, not just generated.
 *
 * Core already permits WebP; AVIF depends on the WordPress version, and
 * clients hand over both. Both are gated on the editor actually supporting
 * the format so an unsupported upload can't land unresizable.
 *
 * @param array $mimes Allowed mime types.
 * @return array
 */
function superrad_allow_modern_image_uploads( $mimes ): array {
	$mimes = (array) $mimes;

	if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$mimes['webp'] = 'image/webp';
	}

	if ( wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) {
		$mimes['avif'] = 'image/avif';
	}

	return $mimes;
}

add_filter( 'upload_mimes', 'superrad_allow_modern_image_uploads' );
