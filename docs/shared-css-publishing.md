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

## Dry run and locked check-then-write

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

`wp_update_custom_css_post()` takes no expected-hash or conditional-write
argument, so a bare read-hash-then-write is not atomic: a Customizer save, or a
second rail request, can update the custom-CSS post in the gap between the
`PUT` handler's own read and its call into that function, and the write would
silently land on top of it. The design is not a compare-and-set; it is a
**lock-serialized check-then-write with post-write verification**, described
below. Every other section of this document that refers to "compare-and-set"
or an "optimistic guard" means this locked sequence.

### The lock

On entry, the `PUT` handler acquires a named MySQL advisory lock through
`$wpdb`: `SELECT GET_LOCK('rt_shared_css_publish', <timeout_seconds>)`. A
return of `0` (timeout) or `NULL` (error) fails the request closed with no
write attempted. On exit — success, validation failure, or exception — the
handler calls `SELECT RELEASE_LOCK('rt_shared_css_publish')` in a `finally`
block so the lock is never held past the request.

`GET_LOCK` is scoped to the MySQL session that acquired it (the `$wpdb`
connection, which WordPress normally keeps open for the request), so the lock
disappears if that connection drops without releasing it; the request-scoped
lifetime and the timeout bound how long a crashed request can hold it stale.
Where `GET_LOCK` is unavailable (managed database services that block it, or a
replica/proxy topology where writer connections aren't guaranteed to land on
the same backend), fall back to an `add_option()`-based lock: `add_option()`
fails if the option row already exists, which is the same INSERT-uniqueness
guarantee a database primary key gives, so it can serve as a mutex primitive.
The fallback lock option stores an acquisition timestamp and is treated as
stale and reclaimable after a fixed TTL, since there is no session teardown to
release it automatically.

A **transient** (`set_transient()` / `get_transient()`) is not acceptable as
the primary lock: transients are commonly backed by an external object cache
(Memcached, Redis) whose read-then-write is not itself atomic, so two
concurrent requests can both see the transient absent and both proceed. Using
a transient to guard the thing that is supposed to prevent a race would
reintroduce the same race one layer up.

### Bringing the Customizer under the same lock

Rail writers alone are not the only path to the custom-CSS post; Appearance →
Customize → Additional CSS writes it too. The plugin hooks `customize_save`
(fires before the Customizer's own save; acquires `GET_LOCK` with a bounded
wait) and `customize_save_after` (fires after; releases it). If the bounded
wait on `customize_save` times out, the hook adds a `WP_Error` that stops the
Customizer save and surfaces a notice to the person saving, rather than
letting the save proceed unlocked. This serializes Customizer saves against
rail writes and against each other.

This hook pair is a plugin responsibility, not a guarantee of the storage
layer: if the hook is not installed, disabled, or a future core change alters
when it fires, only rail writers are serialized against each other, and this
document says so plainly rather than implying broader coverage — see
"Residual risks" below.

### Inside the lock: read, compare, write, verify

Once `GET_LOCK` returns success, the handler runs this sequence without
releasing the lock in between:

1. Re-read the live post with `wp_get_custom_css_post()` (not the value from
   any earlier `GET` request or from the CLI's own prior read). Hash its
   `post_content` and also capture its `post_modified_gmt`.
2. Compare that live hash to `expected_live_sha256` from the request and to
   the approval's bound live hash. Compare the request's `source_sha256`
   against the CSS being written. Any mismatch aborts with no write and
   releases the lock.
3. Save the current custom-CSS post as a revision (see "Revision, audit, and
   rollback" below), then call
   `wp_update_custom_css_post($css, ['stylesheet' => get_stylesheet()])`.
4. Read the post back, again with `wp_get_custom_css_post()`. Require the
   stored CSS hash to equal the approved source hash and `post_modified_gmt`
   to have advanced past the value captured in step 1. Either failure means
   the write did not land as intended — restore the pre-change revision
   captured in step 3 and fail the publish loudly (no partial or best-effort
   success).
5. Release `GET_LOCK`.

Holding the lock across all five steps is what makes step 2's comparison mean
anything: without it, a second writer could still slip in between the compare
and the write, which is exactly the gap the original compare-and-set design
left open. The `post_modified_gmt` check in step 4 guards against a narrower
failure mode than the hash alone — a write that round-trips to the same bytes
(for example, a retried request) should still show a newer revision, so a
verification that only checked the hash could mask a core-level failure to
persist at all.

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
reverse diff, and publish it through the same lock-serialized check-then-write
and post-write verification described above. The current bad version becomes
another recoverable revision. Emergency manual rollback is Appearance →
Customize → Additional CSS using that same recorded revision — which, since it
is now hooked, itself acquires and releases the shared lock.

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
lock-acquisition timeout, post-write verification failure, revision failure,
or validation failure. The plugin and CLI test suite must cover permission
denial, immutable target selection, unknown-field and size rejection, exact
hash matching, a simulated concurrent rail request held against the lock, a
simulated concurrent Customizer save held against the lock, a lock-acquisition
timeout on both paths, a post-write verification failure that triggers
automatic revision restore, idempotent no-op, revision creation, core update
failure, secret redaction, and rollback through the same guarded path.

The plugin package may contain only this route and its activation/deactivation
role cleanup. The repository publisher may read only the canonical CSS file
and its CSS-specific secrets. Neither component may change posts, pages,
plugins, templates, navigation, media, users, general options, or other theme
settings.

### Residual risks

The lock and the hook pair cover writes that go through WordPress's own
save paths. They do not cover:

- **Direct SQL or WP-CLI writes.** `wp post update`, a direct database
  console, or any code that writes the `custom_css` post's content without
  going through `wp_update_custom_css_post()` or the Customizer's save action
  bypasses both `GET_LOCK` calls entirely. Nothing in this design detects or
  prevents that; it is out of scope for a plugin-level lock.
- **Lock timeout behavior under contention.** A bounded wait means a request
  that arrives while the lock is held can fail rather than queue
  indefinitely. That is the intended fail-closed behavior for the rail, but a
  person saving in the Customizer during a rail publish will see a save
  failure and have to retry — this is a real, if narrow, usability cost of
  serializing the two paths.
- **MySQL replica topology and connection pooling.** `GET_LOCK` is scoped to
  a single MySQL connection/session on one backend. If the hosting
  environment routes writes through a connection pooler or a
  read/write-split replica setup where different requests can land on
  different backend connections without a stable primary, `GET_LOCK` may not
  serialize them as intended — this is the condition the `add_option()`
  fallback exists for, and rollout must confirm which situation the
  production host is in before relying on `GET_LOCK` alone.
- **A crashed lock holder before its timeout.** `GET_LOCK` releases when its
  MySQL session ends, but a process that dies without ending its own
  connection cleanly (rare, but possible under some hosting/process-manager
  configurations) can hold the lock until the connection is reaped rather
  than until the request's nominal timeout.

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
- WordPress core `wp_get_custom_css_post()`:
  https://developer.wordpress.org/reference/functions/wp_get_custom_css_post/
- WordPress hook `customize_save`:
  https://developer.wordpress.org/reference/hooks/customize_save/
- WordPress hook `customize_save_after`:
  https://developer.wordpress.org/reference/hooks/customize_save_after/
- WordPress core `add_option()`:
  https://developer.wordpress.org/reference/functions/add_option/
- WordPress REST routes and mandatory permission callbacks:
  https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
- WordPress Application Passwords:
  https://developer.wordpress.org/advanced-administration/security/application-passwords/

