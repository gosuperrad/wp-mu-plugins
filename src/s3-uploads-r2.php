<?php

/**
 * Plugin Name: S3 Uploads (S3-compatible endpoint)
 * Description: Wires S3_UPLOADS_ENDPOINT into humanmade/s3-uploads and forces
 *              path-style URLs, needed for bucket names containing dots
 *              (their TLS cert doesn't cover the resulting virtual-hosted
 *              subdomain). Works against Cloudflare R2, DigitalOcean Spaces,
 *              or any other S3-compatible endpoint set via .env.
 */

add_filter('s3_uploads_s3_client_params', function (array $params): array {
    if (defined('S3_UPLOADS_ENDPOINT') && S3_UPLOADS_ENDPOINT) {
        $params['endpoint'] = S3_UPLOADS_ENDPOINT;
        $params['use_path_style_endpoint'] = true;
    }

    return $params;
});
