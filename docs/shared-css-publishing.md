# Guarded shared-CSS publishing rail

Status: design only for `rtai-lqcf`. Nothing in this document creates a
credential, registers an endpoint, or changes the public site.

## Decision

Publish only `assets/css/shared.css` through a small Real Treasury WordPress
plugin endpoint. The endpoint calls WordPress core's
`wp_update_custom_css_post($css, ['stylesheet' => get_stylesheet()])`; it does
not write the `custom_css` post directly. This is the same core storage path
used by Appearance → Customize → Additional CSS and preserves WordPress
revisions and the active theme's `custom_css_post_id`.

The stock posts endpoint is not the rail. `custom_css` is an implementation
post type, and granting a deployment identity general post or theme-editing
authority would make the credential broader than this job. The plugin instead
owns exactly two authenticated routes under `rt-css/v1/shared`:

- `GET` returns the active stylesheet slug, current CSS, SHA-256 of its exact
  UTF-8 bytes, custom-CSS post ID, and latest revision ID. It has no side
  effects.
- `PUT` accepts only `css`, `expected_live_sha256`, `source_sha256`,
  `source_commit`, and `approval_ref`. It updates the active stylesheet only.
  Unknown fields, a non-string CSS value, invalid UTF-8, a body over 512 KiB,
  an empty approval reference, or a source hash mismatch are rejected.

Both routes use a required `permission_callback` checking one plugin-defined
capability, `rt_publish_shared_css`. The callback also refuses unless the
active stylesheet is the installation's recorded Astra stylesheet slug. It
never accepts a stylesheet, post ID, option name, path, URL, or operation from
the caller.

## Least privilege and credentials

Create a dedicated `rt-css-publisher` WordPress user and a dedicated role with
only `read` and `rt_publish_shared_css`. Do not grant `edit_posts`,
`edit_pages`, `edit_theme_options`, `install_plugins`, `activate_plugins`,
`manage_options`, upload, navigation, template, or user capabilities. The
plugin checks the custom capability before calling the core function; the user
cannot use the ordinary content endpoints to edit the site.

Authenticate over HTTPS with a WordPress Application Password named
`rt-css-publish`. Application Passwords are API credentials, are stored hashed,
are shown once, and can be revoked independently of the user's login password.
Store the username and password in the platform's managed secret store under
CSS-specific names; never in this repository, command arguments, logs, diffs,
or approval artifacts. The publisher reads only those named keys. Credential
creation, role assignment, plugin activation, and secret installation are
owner-only setup steps.

## Dry run and optimistic guard

The future repository command is two phase:

1. `shared_css_publish.py plan` requires a clean checkout and the canonical
   tracked path. It rejects symlinks and any path override, reads `GET`, hashes
   source and live bytes, and prints a human-readable unified diff with the
   active stylesheet, source commit, source/live hashes, byte counts, and
   proposed hash. It writes no WordPress state.
2. The owner reviews that diff and gives an approval reference bound to the
   source commit, source SHA-256, live SHA-256, and target site. A later
   `apply --approval-ref ... --expected-live-sha256 ... --source-commit ...`
   re-reads both source and live state. It refuses if HEAD/source changed, the
   live hash changed out of band, the approval tuple differs, the repository is
   dirty, or the active stylesheet is unexpected. There is no force flag.

`PUT` repeats the live-hash compare while handling the request immediately
before `wp_update_custom_css_post()`. This closes the race between the CLI's
second `GET` and the write. Equal source/live hashes return an idempotent
`unchanged` result and create no revision.

## Revision, audit, and rollback

Before changing CSS, the plugin explicitly saves the current custom-CSS post
as a revision and records its ID. A successful response and an owner-only local
receipt contain: timestamp, actor user ID, target origin, stylesheet, approval
reference, source commit, old/new SHA-256, pre-change revision ID, resulting
post ID, and resulting revision ID. CSS and credentials are not copied into the
receipt.

Rollback uses the same guarded `PUT`, not a broader restoration endpoint: read
the named pre-change revision in wp-admin, save its exact CSS to a temporary
owner-only file, plan it against the current live hash, approve that explicit
reverse diff, and publish it with the same compare-and-set checks. The current
bad version becomes another recoverable revision. Emergency manual rollback is
Appearance → Customize → Additional CSS using that same recorded revision.

## Post-publish verification

The command does not report success from a 2xx response alone. It must:

1. Repeat authenticated `GET` and require the live SHA-256 to equal the source
   SHA-256 and the active stylesheet to be unchanged.
2. Fetch representative public pages with a unique cache-busting query string
   and verify the page's `wp-custom-css` block contains a deterministic marker
   and rules from the new source. Never purge a broad cache automatically.
3. Run screenshot checks at phone (390×844), tablet (768×1024), and desktop
   (1440×1000) on the homepage plus one insight, webinar, ERR NOT, and IV&V
   page. Record URLs and screenshots; check navigation, overflow, readable
   text, forms, CTA focus states, and iframe sizing.
4. If any check fails, stop, present the reverse diff, and require a separate
   rollback approval. Do not make page-level compensating edits.

## Failure posture and implementation tests

Every uncertainty fails closed and leaves WordPress unchanged: authentication
failure, missing capability, wrong site/theme, malformed response, hash drift,
timeout, revision failure, or validation failure. The plugin and CLI test suite
must cover permission denial, immutable target selection, unknown-field and
size rejection, exact hash matching, a simulated concurrent edit, idempotent
no-op, revision creation, core update failure, secret redaction, and rollback
through the same guarded path.

The plugin package may contain only this route and its activation/deactivation
role cleanup. The repository publisher may read only the canonical CSS file
and its CSS-specific secrets. Neither component may change posts, pages,
plugins, templates, navigation, media, users, general options, or other theme
settings.

## Implementation and rollout gates

Implementation is a separate reviewed task and PR. Rollout order is: plugin
tests → CLI tests against a disposable WordPress fixture → owner review →
plugin install/activation → dedicated user/capability → Application Password →
secret installation → read-only production `plan` → explicit publish approval
→ one guarded write → verification. Each production mutation remains
human-approved and individually auditable.

## Primary references

- WordPress core `wp_update_custom_css_post()`:
  https://developer.wordpress.org/reference/functions/wp_update_custom_css_post/
- WordPress REST routes and mandatory permission callbacks:
  https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
- WordPress Application Passwords:
  https://developer.wordpress.org/advanced-administration/security/application-passwords/

