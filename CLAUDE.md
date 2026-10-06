# wp-mu-plugins

Operating guidance for Claude Code sessions in this repo. `README.md` is the
human-facing overview and is not repeated here.

**What each module does, and why each load-bearing detail is the way it is,
lives in bedrock-coolify's `CLAUDE.md`**, under "WebP output is a WordPress
filter", "Anonymous HTML sends `Cache-Control: no-cache`", "The staging mail
guard needs two filters" and "`DISABLED_PLUGINS` needs two filters". It stays there because every site copies that file, and
the reasoning is about how the sites behave. Read the relevant section before
changing a module. This file covers the package itself.

## Why a package, and why not the base image

These files were copied into every site repo and drifted: three bugs found on
2026-09-09 were all a stale copy (bedrock-coolify#29). The runtime base image
could not take them, because **the base can hold anything DDEV replaces and
nothing DDEV needs**, and DDEV serves the repo tree directly. A Composer
package installs in both places.

The ceiling is lower than the base image's: a release reaches a site only when
its `composer.lock` is bumped and committed. What it buys is that the per-site
change is a lockfile bump, which cannot be misapplied, rather than a hand
patch. Automating those bumps is bedrock-coolify#46.

## The loader is the only headed file at the root, on purpose

Composer installs this package as `web/app/mu-plugins/wp-mu-plugins/`.
WordPress never loads mu-plugin subdirectories. Bedrock's autoloader does, and
it loads **every** `.php` file with a `Plugin Name` header exactly one level
down. So `superrad-mu-plugins.php` is found and the modules in `src/` (two
levels down) are not, and load order is decided by the loader rather than by
a directory listing.

The modules keep their own headers. They are documentation, and harmless two
levels down. Do not move a module up to the root: it would be loaded by the
autoloader as well as the loader, and every module defines named functions, so
that is a fatal "Cannot redeclare". `tests/structure.sh` fails on it.

## Nothing here runs until WordPress is installed

Bedrock's autoloader wraps everything in `is_blog_installed()`. As single
files in a site repo these modules loaded on every request, including during
`wp core install`. As a package they do not load until the install has
finished.

The only visible difference is the admin notification `wp core install`
sends: the staging mail guard is not active yet, so it goes to the admin
address the installer was given rather than to `MAIL_REDIRECT_TO`. That is the
human running `bin/bootstrap-site.sh`. Nothing else here acts before install:
there are no active plugins for `DISABLED_PLUGINS` to filter and no uploads to
convert.

## Reach siblings through `__DIR__`, never `WPMU_PLUGIN_DIR`

`WPMU_PLUGIN_DIR` is the mu-plugins root, not this package's directory.
`superrad-mu.php` loaded the login module through it when both were loose files
in that root, and that line is the only code that had to change in the move.
`tests/structure.sh` fails on the constant anywhere under `src/`.

`plugins_url( '', __FILE__ )` in the login module is fine. WordPress resolves
it for files anywhere under `WPMU_PLUGIN_DIR`.

## Coding standard

These files follow WordPress coding standards (tabs, `array()`, WordPress
naming), except `s3-uploads-r2.php`, which came from the starter's PER-styled
code. Neither is auto-formatted here, and nothing should reformat them: a
style-only diff hides the one line that changed in a review of a module this
security-relevant. CI runs `php -l` on the declared floor (8.0) and on the
estate's versions.

## Before tagging

CI here cannot load WordPress. The acceptance test is bedrock-coolify's
`docker-e2e-test`, which installs WordPress and exercises `DISABLED_PLUGINS`
on its read and write paths, all three environments, WebP output and cron.

Run it against an untagged commit before tagging. Push the change to a branch
here, require it from a bedrock-coolify branch by name (`"superrad/wp-mu-plugins":
"dev-<branch>"`), run the suite there, then tag and put the constraint back to a
tag range before that branch merges.

**Not a `path` repository**, which is the recipe `superrad/site-checks` uses and
is the obvious thing to reach for. site-checks runs on the host from
`vendor/bin`. This package is installed by `composer install` **inside the
Docker build's vendor stage**, where a sibling checkout is outside the build
context, so a `path` repository either fails the build or tests nothing.
