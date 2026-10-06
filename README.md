# superrad/wp-mu-plugins

Super Rad's shared WordPress must-use plugins, for Bedrock sites built from
[`gosuperrad/bedrock-coolify`](https://github.com/gosuperrad/bedrock-coolify).
Until this package existed every site carried its own copy of these files, and
a fix to one of them was a pull request per site repo
([bedrock-coolify#29](https://github.com/gosuperrad/bedrock-coolify/issues/29)).

| Module | What it does |
| --- | --- |
| `src/superrad-mu.php` | `DISABLED_PLUGINS` (read and write filters), dashboard widget cleanup, anonymous `?author=N` enumeration block. Loads the custom login module. |
| `src/superrad-mail-guard.php` | Reroutes all outbound mail to `MAIL_REDIRECT_TO` when it is set, on both `wp_mail` and `gform_notification`. |
| `src/superrad-image-formats.php` | WebP (or AVIF) output for uploaded JPEGs, via WordPress's own `image_editor_output_format` filter. |
| `src/s3-uploads-r2.php` | Points `humanmade/s3-uploads` at `S3_UPLOADS_ENDPOINT` with path-style URLs. Inert unless that constant is set. |
| `src/superrad-custom-login/` | The Super Rad login screen styling. |
| `src/superrad-html-cache.php` | Sends `Cache-Control: no-cache` on anonymous front-end HTML, which a Cloudflare HTML cache rule respecting the origin's browser TTL relies on. The `superrad_html_cache_control` filter changes or disables it. |

## Installing

Not on Packagist. Add a `vcs` repository and require it:

```json
"repositories": [
  { "name": "wp-mu-plugins", "type": "vcs", "url": "https://github.com/gosuperrad/wp-mu-plugins" }
],
"require": {
  "superrad/wp-mu-plugins": "^1.0"
}
```

Bedrock's `installer-paths` puts it at `web/app/mu-plugins/wp-mu-plugins/`,
which the starter's `.gitignore` already ignores, and Bedrock's autoloader
loads `superrad-mu-plugins.php` from there.

**Delete any tracked copy of these files first, in the same commit.** Every
module defines named functions, so a site that still has
`web/app/mu-plugins/superrad-mu.php` alongside the package fails with
"Cannot redeclare" on every request. bedrock-coolify's `estate-drift` check
reports a leftover copy.

## Releasing

A new version reaches a site only when that site's `composer.lock` is bumped
and committed. Tag `vX.Y.Z` here, then bump each site. See CLAUDE.md for what
to verify before tagging.
