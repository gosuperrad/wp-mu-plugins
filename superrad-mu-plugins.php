<?php
/**
 * Plugin Name: Super Rad MU Plugins
 * Plugin URI: https://github.com/gosuperrad/wp-mu-plugins
 * Description: Super Rad's shared must-use plugins: DISABLED_PLUGINS, the staging mail guard, WebP output, the S3-compatible endpoint shim, the custom login screen and Cache-Control on anonymous HTML.
 * Author: ⚡️ Super Rad
 * Author URI: https://superrad.dev
 * Version: 1.1.0
 * Requires PHP: 8.0
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * The only file in this package with a Plugin Name header at its root, and
 * that is deliberate. Composer installs the package as a directory under
 * web/app/mu-plugins/, WordPress never loads mu-plugin subdirectories itself,
 * and Bedrock's autoloader loads every headed .php file ONE level down. So
 * this file is found and the modules under src/ are not, which leaves the
 * load order below in this file's hands rather than in a directory listing.
 *
 * A consequence worth knowing: Bedrock's autoloader only runs once WordPress
 * is installed (is_blog_installed()), so none of this is active during
 * `wp core install`. See CLAUDE.md.
 */

require_once __DIR__ . '/src/superrad-mu.php';
require_once __DIR__ . '/src/superrad-mail-guard.php';
require_once __DIR__ . '/src/superrad-image-formats.php';
require_once __DIR__ . '/src/s3-uploads-r2.php';
require_once __DIR__ . '/src/superrad-html-cache.php';
