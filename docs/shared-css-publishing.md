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
owns exactly three authenticated routes under `rt-css/v1/shared`:

- `GET` returns the active stylesheet slug, current CSS, SHA-256 of its exact
  UTF-8 bytes, custom-CSS post ID, and latest revision ID. It has no side
  effects.
- `POST /preflight` accepts only `css`, writes nothing, and answers whether
  those exact bytes would survive WordPress's own save path unchanged on this
  install, as this user. It grants nothing and approves nothing; it only
  reports. See "Filtering preflight" below.
- `PUT` accepts only `css` and `approval_ref`. It updates the active stylesheet
  only. Every hash the write is checked against comes from the server-side
  approval record named by that reference, never from the request — see
  "Approval is a server-side record". Unknown fields, a non-string CSS value,
  invalid UTF-8, a body over 512 KiB, a missing reference, or CSS whose hash
  does not match the record are rejected.

All three routes use a required `permission_callback` checking one plugin-defined
capability, `rt_publish_shared_css`. The callback also refuses unless the
active stylesheet is the installation's recorded Astra stylesheet slug. It
never accepts a stylesheet, post ID, option name, path, URL, or operation from
the caller.

## Least privilege and credentials

Create a dedicated `rt-css-publisher` WordPress user and a dedicated role whose
stored capability list is only `read` and `rt_publish_shared_css`. Do not grant
`edit_posts`, `edit_pages`, `edit_theme_options`, `install_plugins`,
`activate_plugins`, `manage_options`, `unfiltered_html`,
`rt_approve_shared_css`, upload, navigation, template, or user capabilities.
The plugin checks the custom capability before calling the core function; the
user cannot use the ordinary content endpoints to edit the site, and it cannot
mint the approval its own writes require.

One capability decision does have to go the publisher's way — `unfiltered_html`,
for the reason set out immediately below. It is granted by a `map_meta_cap`
filter scoped to that role and that decision, and it is never written into the
role's capability list.

Authenticate over HTTPS with a WordPress Application Password named
`rt-css-publish`. Application Passwords are API credentials, are stored hashed,
are shown once, and can be revoked independently of the user's login password.
Store the username and password in the platform's managed secret store under
CSS-specific names; never in this repository, command arguments, logs, diffs,
or approval artifacts. The publisher reads only those named keys. Credential
creation, role assignment, plugin activation, and secret installation are
owner-only setup steps.

### Where WordPress filters custom CSS, exactly

Verified against WordPress 7.1 source rather than recalled:

- `wp_update_custom_css_post()` performs no capability check and no CSS
  sanitization of its own. It applies the `update_custom_css_data` filter to
  the `css`/`preprocessed` pair, then hands the result to `wp_update_post()` —
  or `wp_insert_post()`, if no post exists yet — as `post_content`.
- `wp_insert_post()` calls `sanitize_post( $postarr, 'db' )`. In `db` context
  `sanitize_post_field()` applies `pre_post_content` and then the dynamic
  `content_save_pre` filter to the content. That is where filtering happens,
  and it happens for every post type, `custom_css` included.
- `kses_init()` runs on `init` and on `set_current_user`. If
  `current_user_can( 'unfiltered_html' )` is false, it calls
  `kses_init_filters()`, which adds `wp_filter_post_kses` to `content_save_pre`
  (alongside `wp_filter_global_styles_post` at priority 9, which no-ops on
  anything that is not a global-styles JSON payload). `wp_filter_post_kses()`
  is `addslashes( wp_kses( stripslashes( $data ), 'post' ) )`.
- `wp_kses()` is HTML-aware, not CSS-aware. Its first normalization step,
  `wp_kses_normalize_entities()`, replaces every `&` with `&amp;`, and it
  rewrites `<`-initiated sequences. Stylesheets legitimately contain both — an
  `&` inside a `url()` data URI or an attribute selector, a `<` inside such a
  payload — so KSES cannot be assumed to round-trip CSS byte for byte. A rail
  that writes as a user without `unfiltered_html` and then requires the stored
  `post_content` hash to equal the repository source is asserting something
  KSES does not promise.

So a direct `wp_update_custom_css_post()` call runs the
`update_custom_css_data` filter and the post-save filters — KSES included when
the acting user lacks `unfiltered_html` — and nothing else. It does not run
`WP_Customize_Custom_CSS_Setting::validate()`, no `customize_validate_*` or
`customize_sanitize_*` filter, and no capability check of its own. A Customizer
save additionally runs that setting's `validate()` — which since WordPress 7.0
rejects a `</style` sequence or a trailing prefix of one, and before that
rejected any `</?\w+` markup — and its declared `capability`, `edit_css`,
before reaching the same `wp_update_custom_css_post()` and the same post-save
filters. An administrator normally holds `unfiltered_html`, so KSES is not
registered on that request at all. That is why Additional CSS appears to store
CSS verbatim in wp-admin, and why it would not for a low-privileged publisher.

One correction to the finding that prompted this section: there is no
`unfiltered_css` capability in WordPress core — a search of the 7.1 tree
returns no occurrence. The meta capability in the custom-CSS path is `edit_css`,
and `map_meta_cap()` handles it in the same `case` as `unfiltered_html`: with
`DISALLOW_UNFILTERED_HTML` defined and true it resolves to `do_not_allow`; on
multisite for a non-super-admin it resolves to `do_not_allow`; otherwise it
resolves to the primitive `unfiltered_html`. The mechanism described was real;
only the capability name was wrong.

### The narrow grant

The plugin registers a `map_meta_cap` filter — the filter at the end of
`map_meta_cap()`, which every `WP_User::has_cap()` call passes through — that:

- acts only when the capability being resolved is `unfiltered_html`, and
  deliberately not `edit_css`: the `custom_css` post type is registered with
  `'edit_post'` and `'edit_posts'` mapped to `edit_css`, so granting `edit_css`
  would hand the publisher edit authority over that post type through any other
  code path that checks it. Nothing in the rail's own write path checks
  `edit_css` — `wp_update_custom_css_post()`, `wp_update_post()`, and
  `wp_save_post_revision()` perform no capability checks — so the narrower
  grant is sufficient;
- acts only when the user being tested holds the `rt-css-publisher` role;
- returns core's answer untouched if core already put `do_not_allow` in the
  required-capability list, so the `DISALLOW_UNFILTERED_HTML` and multisite
  rules still win;
- otherwise replaces the required-capability list with
  `array( 'rt_publish_shared_css' )`.

The effect is that `current_user_can( 'unfiltered_html' )` is true for that one
user, because that user holds `rt_publish_shared_css`, so `kses_init()` does
not register KSES on its requests. The role's stored capabilities never include
`unfiltered_html`: nothing that reads role capabilities sees the publisher as
an unfiltered-HTML user, no other user is affected, and no other capability is
granted. `map_meta_cap` is the right hook rather than `user_has_cap` because
`map_meta_cap` decides what a capability *requires*, while `user_has_cap` would
mean injecting `unfiltered_html` into the user's effective capability array —
the broader thing we are avoiding.

State the consequence plainly: with the grant in place, the publisher can store
arbitrary bytes, markup included, in the `custom_css` post's content. What
bounds that is not KSES but the rest of the rail — one canonical tracked source
file, a reviewed diff, an approval bound to the source hash, and the markup
rule the preflight enforces.

### Hosts where the grant cannot apply

If the host defines `DISALLOW_UNFILTERED_HTML` as true, or the install is
multisite and the publisher is not a super admin, `map_meta_cap()` resolves the
capability to `do_not_allow` and the plugin does not override it. KSES then
runs on the rail's write and the exact-byte verification would fail by design.
The rail refuses instead: the preflight detects the divergence before any
write, the plugin reports the host condition by name, and shared CSS stays a
manual task on that install until the owner changes the host's own setting.

The plugin must not call `kses_remove_filters()`, unhook `wp_filter_post_kses`,
or otherwise strip the filter around its own write to work around this. That
would be a plugin quietly reversing a site-wide security decision the host
operator made deliberately, and it is out of scope for a publishing rail.

## Approval is a server-side record

An approval the publisher can type is not an approval. In the previous revision
`approval_ref` was caller-supplied text and the expected hashes came out of the
same request body, so anyone holding the Application Password could publish
arbitrary CSS by computing matching hashes and inventing a reference. The
rail's central claim — that a person approved these exact bytes against that
exact live state — was unenforceable. Approval is therefore a record the
publisher identity cannot create.

**Who approves.** A separate capability, `rt_approve_shared_css`, is added to
the administrator role at plugin activation and to no other role. Approvals are
minted only from an authenticated wp-admin session, through a small screen the
plugin registers, or from WP-CLI running as an administrator. The
`rt-css-publisher` role does not hold `rt_approve_shared_css`, and the scoped
`map_meta_cap` filter described above does not touch it.

**What is stored.** Each approval is one record in a private custom post type,
`rt_css_approval`, registered with `'public' => false`,
`'show_in_rest' => false`, and its create, edit, and delete capabilities all
mapped to `rt_approve_shared_css`:

| Field | Meaning |
| --- | --- |
| `source_sha256` | SHA-256 of the exact bytes approved for publication |
| `expected_live_sha256` | SHA-256 of the live CSS the approver saw, read by the server, not supplied |
| `source_commit` | Repository commit those bytes came from |
| `stylesheet` | Stylesheet slug this approval is valid for |
| `approver_user_id` | The administrator who minted it |
| `created_gmt` | Mint time |
| `expires_gmt` | Mint time plus a short TTL, 30 minutes by default and capped by the plugin |
| `state` | `open`, `consumed`, or `failed` — single use in every case |

The minting screen reads the live hash itself rather than accepting one. The
approver supplies the source hash and commit from the `plan` output after
reviewing the diff. The screen refuses to mint a record whose `source_sha256`
already equals the live hash, since there would be nothing to publish, and
refuses when the stylesheet is not the install's recorded Astra slug.

**What `PUT` does with it.** The request carries `css` and `approval_ref` and
nothing else. The handler loads the record and refuses unless the record
exists, its `state` is `open`, `expires_gmt` is in the future, its `stylesheet`
matches the active stylesheet, and the SHA-256 of the submitted CSS equals the
record's `source_sha256`. The expected live hash used in the locked comparison
is read from the record. No hash in the request body is trusted, because there
is no hash in the request body.

**Consumption.** Inside the lock, after a read-back-verified write, the handler
sets `state` to `consumed` and records the resulting post ID and revision ID on
the record. If verification fails, it sets `state` to `failed`. Either way the
record is spent: there is no automatic retry, and a second attempt requires a
new approval from an administrator. Both transitions happen while the lock is
held, so two concurrent requests cannot spend the same record.

**What the CLI can and cannot do.** `shared_css_publish.py plan` computes and
displays the source hash, the live hash, byte counts, and the diff. It cannot
mint an approval, and the credential it holds cannot either. The owner reads
the plan output, opens wp-admin as an administrator, mints the record there,
and `apply` passes back only the reference.

**Alternative considered and not adopted.** An HMAC signature over the approved
tuple, with the key held by the approver and verified by the plugin, would also
bind an approval without a stored record. We are not taking it: it puts a
long-lived signing key somewhere an operator has to manage, it leaves no
server-side audit row, and it has no natural single-use or expiry semantics —
all three of which the record gets for free.

## Dry run and locked check-then-write

The future repository command is two phase:

1. `shared_css_publish.py plan` requires a clean checkout and the canonical
   tracked path. It rejects symlinks and any path override, reads `GET`, hashes
   source and live bytes, calls `POST /preflight` with the candidate bytes,
   and prints a human-readable unified diff with the active stylesheet, source
   commit, source/live hashes, byte counts, and proposed hash. It writes no
   WordPress state.
2. The owner reviews that diff and mints an approval record in wp-admin as an
   administrator, bound to the source commit, the source SHA-256, the live
   SHA-256 the server reads for itself, and the stylesheet. A later
   `apply --approval-ref ...` re-reads source and live state locally and sends
   only the CSS and that reference. The rail refuses if HEAD or the source
   changed, the repository is dirty, the reference is unknown, expired, already
   spent, or bound to different bytes, the live hash no longer matches the
   record, or the active stylesheet is unexpected. There is no force flag.

`wp_update_custom_css_post()` takes no expected-hash or conditional-write
argument, so a bare read-hash-then-write is not atomic: a Customizer save, or a
second rail request, can update the custom-CSS post in the gap between the
`PUT` handler's own read and its call into that function, and the write would
silently land on top of it. The design is not a compare-and-set; it is a
**lock-serialized check-then-write with post-write verification**, described
below. Every other section of this document that refers to "compare-and-set"
or an "optimistic guard" means this locked sequence.

### Filtering preflight

Nothing between `plan` and `apply` should be able to surprise the exact-byte
check. The `preflight` route answers one question — if this exact string were
written now, by this user, on this install, would the stored `post_content` be
these same bytes? — by running the candidate through the same chain a write
would, and writing nothing:

1. the `update_custom_css_data` filter, exactly as `wp_update_custom_css_post()`
   applies it;
2. the whole `db`-context save chain, not one filter of it: build the same
   `$post_data` array `wp_update_custom_css_post()` builds, `wp_slash()` it as
   that function does, run `sanitize_post( $post_data, 'db' )` — the call
   `wp_insert_post()` makes — and `wp_unslash()` the resulting `post_content`.
   In `db` context `sanitize_post_field()` applies `pre_post_content` before
   `content_save_pre`, so simulating `content_save_pre` alone would miss a
   filter the real write runs and could pass while the write changes the bytes.
   Running it as the publisher means KSES is present in the chain exactly when
   it would be present on the write;
3. the markup rule `WP_Customize_Custom_CSS_Setting::validate()` enforces, so
   content the rail accepts is content a later Customizer save would also
   accept;
4. the preconditions the rest of the rail depends on: that the capability grant
   resolves on this host, and that revisions are enabled for the `custom_css`
   post type, since the rollback artifact and the post-write version signal
   both depend on them.

If the result differs from the input by one byte, `preflight` fails, names the
stage that changed it and the first differing offset, and `plan` refuses to
produce an approvable diff. The same check runs again inside the lock,
immediately before the write, against the bytes actually about to be written:
capability state, an object cache, or a plugin update can change between `plan`
and `apply`. With both in place, a post-write verification failure can no
longer mean "a filter rewrote our CSS"; it means the write did not land, which
is what the restore path exists for.

Two caveats rather than a glossed guarantee. The preflight executes whatever
third-party filters are hooked anywhere on that save chain, `pre_post_content`
and `content_save_pre` included, so it is read-only in what it does, not
necessarily in what other code hooked there does. And it is a
prediction: a filter whose behavior depends on time or request context can
still diverge at write time.

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

Rail writers are not the only path to the custom-CSS post; Appearance →
Customize → Additional CSS writes it too. The previous revision hooked
`customize_save` and claimed a `WP_Error` raised there would stop the save. It
cannot. `customize_save` is a `do_action` fired inside
`WP_Customize_Manager::_publish_changeset_values()`; it has no return channel,
and the setting saves run immediately afterward whatever a callback does.

The hook that can refuse is setting validation. `WP_Customize_Setting::validate()`
applies the dynamic filter `customize_validate_{$setting_id}`, and for
Additional CSS the setting ID is `custom_css[<stylesheet>]`, so the filter is
`customize_validate_custom_css[<stylesheet>]`. A `WP_Error` returned there does
propagate: `WP_Customize_Manager::save_changeset_post()` calls
`validate_setting_values()`, counts the invalid settings, and when the update
is transactional — which a publish save from the Customizer is — returns
`new WP_Error( 'transaction_fail', ... )` before writing any setting.
`WP_Customize_Manager::save()` turns that into a JSON error, and the person
saving sees the refusal in the Customizer.

So the plugin:

- registers that filter on `customize_save_validation_before`, which fires
  immediately before `validate_setting_values()` and exists in core for exactly
  this just-in-time purpose;
- acquires `GET_LOCK` in the filter with the same bounded wait the rail uses.
  On success it returns the validity object untouched; on timeout it returns a
  `WP_Error` — "a shared CSS publish is in progress, try again" — which refuses
  the entire save rather than letting it write unlocked;
- releases on `customize_save_after`, which fires at the end of
  `_publish_changeset_values()` once the settings have been written;
- keeps a request-scoped flag for whether it holds the lock, acquires at most
  once per request, and releases once on `shutdown` if it still holds it. Both
  halves matter: the validate path also runs for changeset saves that never
  publish, which never reach `customize_save_after`, and MySQL's `GET_LOCK` is
  re-entrant per session, so a second acquisition of the same name would need a
  second `RELEASE_LOCK` to clear.

Even if all of that fails, nothing holds the lock past the request. `GET_LOCK`
is scoped to the MySQL session; the session ends when the request ends, and
anything it held is released then. An aborted or fatal save costs at most the
remainder of that request.

This is a plugin responsibility, not a storage-layer guarantee. Two paths reach
`_publish_changeset_values()` without passing through the validate filter: a
scheduled changeset published by WP-Cron, and any code that transitions a
`customize_changeset` post to `publish` directly. The plugin still acquires on
`customize_save` for those, with a bounded wait, but that hook cannot refuse —
so a failed acquisition there is recorded loudly rather than enforced. If the
plugin is deactivated, or a future core change moves these hooks, only rail
writers are serialized against each other. See "Residual risks" below.

### Inside the lock: read, compare, preflight, write, verify

Once `GET_LOCK` returns success, the handler runs this sequence without
releasing the lock in between:

1. Load the approval record named by `approval_ref` and check it is open,
   unexpired, and bound to the active stylesheet. Re-read the live post with
   `wp_get_custom_css_post()` (not the value from any earlier `GET` request or
   from the CLI's own prior read). Hash its `post_content`, hold those exact
   bytes in request memory as the pre-change content, record that pre-change
   hash, and record the post's latest revision ID from
   `wp_get_latest_revision_id_and_total_count()`.
2. Compare that live hash to the record's `expected_live_sha256`, and the
   SHA-256 of the submitted CSS to the record's `source_sha256`. Both expected
   values come from the record; none comes from the request. Any mismatch
   aborts with no write and releases the lock.
3. Re-run the filtering preflight against the bytes about to be written. If it
   reports that any byte would change, or that the host cannot grant the
   capability, abort with no write and release the lock.
4. Save the current custom-CSS post as a revision with `wp_save_post_revision()`
   and record its ID (see "Revision, audit, and rollback" below), then call
   `wp_update_custom_css_post($css, ['stylesheet' => get_stylesheet()])`. A
   null return here is not automatically a failure: `wp_save_post_revision()`
   skips when the latest revision already holds exactly this content, which is
   the normal state right after a previous rail write. In that case record the
   existing latest revision ID as the pre-change revision; treat null as a
   failure only when no revision of the current content exists.
5. Read the post back, again with `wp_get_custom_css_post()`. Require the
   stored CSS hash to equal the record's `source_sha256` and the post's latest
   revision ID to be greater than the one recorded in step 1. Either failure
   means the write did not land as intended: record the hash just observed and
   go to "When post-write verification fails" below, still holding the lock.
6. Mark the approval record spent — `consumed` on a verified write, `failed`
   otherwise — while the lock is still held.
7. Release `GET_LOCK` in the `finally` block, on every branch.

Holding the lock across all seven steps is what makes step 2's comparison mean
anything: without it, a second writer could still slip in between the compare
and the write, which is exactly the gap the original compare-and-set design
left open. Step 3 makes step 5 diagnostic: with the preflight passing on the
same bytes moments earlier, a hash mismatch at read-back can no longer mean "a
filter rewrote our CSS".

The version signal in step 5 is the revision ID, not `post_modified_gmt`. Post
modified timestamps have second precision and are not a monotonic counter: a
write completing in the same second as the previous one can store an identical
`post_modified_gmt`, so requiring it to advance would fail valid publishes and
trigger a pointless restore. Revision IDs are `wp_posts.ID` values from an
auto-increment column, so a newer revision always carries a larger ID. Core
produces one for us — `wp_save_post_revision_on_insert()` is hooked to
`wp_after_insert_post`, and `wp_save_post_revision()` on `post_updated` defers
to it — so the update itself stores a revision holding the new content, on top
of the pre-change revision the handler saved explicitly in step 4. Because the
approver will not mint an approval whose source hash already equals the live
hash, every approved write is a real content change, and `wp_save_post_revision()`
skips a revision only when no revisioned field changed.

`custom_css` is registered with `'supports' => array( 'title', 'revisions' )`,
so revisions apply to it. If an install disables them —
`WP_POST_REVISIONS` false, or a `wp_revisions_to_keep` filter returning 0 —
`wp_save_post_revision()` returns null, there is no rollback artifact and no
version signal, and the rail refuses at preflight rather than writing without
either.

### When post-write verification fails

Recovery runs inside the same held lock. Releasing it first would let the next
writer in ahead of the repair.

Before restoring anything, the handler re-reads the post once more with
`wp_get_custom_css_post()` and hashes it. That re-read hash must equal the hash
observed in the failed read-back in step 5 — the state the rail's own write
produced. Only then is restoring safe. If it differs, something wrote to the
post outside the lock in the interval between our read-back and now: a direct
SQL update or a WP-CLI invocation, the two paths the lock cannot cover.
Restoring would silently destroy that writer's content, so the rail does not
restore.

The restore itself re-writes the pre-change content captured in step 1, through
`wp_update_custom_css_post( $pre_change_css, array( 'stylesheet' => get_stylesheet() ) )`,
rather than calling `wp_restore_post_revision()` on the recorded revision ID.
Either would put the old CSS back; this one is chosen because:

- it is the path the rail already preflights and verifies, so the restore gets
  the same treatment as a publish, including a read-back whose expected value —
  the pre-change hash recorded in step 1 — was known before the write rather
  than read out of the row being repaired;
- `wp_restore_post_revision()` calls `wp_update_post()` directly, so it skips
  `update_custom_css_data` and the `custom_css_post_id` theme-mod bookkeeping
  that `wp_update_custom_css_post()` performs, and it writes `_edit_last` post
  meta attributing the change to the publisher user;
- revision content passes back through `content_save_pre` on its way in
  regardless, so restoring from a revision is not inherently more faithful than
  re-writing bytes we already hold — only less verifiable.

The recorded revision ID stays in the receipt and remains the artifact an
operator uses for a manual restore. The rail simply does not depend on it for
automatic recovery.

| State after the write | What the rail does |
| --- | --- |
| Read-back hash equals the record's `source_sha256` and the latest revision ID is greater than the one recorded in step 1 | Success. Mark the approval `consumed`, release the lock, return the receipt. |
| Verification failed, and the re-read hash equals the hash observed in the failed read-back | Restore the pre-change content through `wp_update_custom_css_post()`; read back and require the stored hash to equal the pre-change hash from step 1; mark the approval `failed`; release the lock; report the publish as failed and the restore as verified. |
| Verification failed, and the re-read hash differs from the hash observed in the failed read-back | Do not restore. Mark the approval `failed`, release the lock, and fail loudly, recording the approved source hash, the hash observed in the read-back, the differing re-read hash, the pre-change hash, and the pre-change revision ID. An out-of-band writer touched the post; what to keep is a human decision. |
| The restore's own read-back does not match the pre-change hash | Stop. Attempt nothing further automatically. Mark the approval `failed`, release the lock, and report all four hashes plus the revision IDs. The site's CSS is in a known-bad state and the next step is manual. |

In every row the approval is spent, the lock is released exactly once in the
`finally` block, and no row retries a write automatically. A retry after any
failure needs a new approval minted by an administrator.

## Revision, audit, and rollback

Before changing CSS, the plugin explicitly saves the current custom-CSS post
as a revision, records its ID, and records the SHA-256 of the pre-change
`post_content` it just read — that hash is what a restore is verified against.
A successful response and an owner-only local receipt contain: timestamp, actor
user ID, target origin, stylesheet, approval reference, approver user ID,
source commit, old/new SHA-256, the latest revision ID recorded before the
write, the pre-change revision ID the handler saved, resulting post ID,
resulting revision ID, and the approval record's resulting state.
A failed publish records the same fields plus every hash named in the recovery
table, and which row of that table was taken. CSS and credentials are not
copied into the receipt.

Rollback uses the same guarded `PUT`, not a broader restoration endpoint: read
the named pre-change revision in wp-admin, save its exact CSS to a temporary
owner-only file, plan it against the current live hash — which runs the same
filtering preflight — mint a fresh approval record for that explicit reverse
diff in wp-admin, and publish it through the same lock-serialized
check-then-write and post-write verification described above. The current bad
version becomes another recoverable revision. Emergency manual rollback is Appearance →
Customize → Additional CSS using that same recorded revision — which, through
the validation filter described above, itself acquires and releases the shared
lock.

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
failure, missing capability, a host that cannot grant the filtering capability,
an unknown, expired, spent, or mismatched approval record, preflight
divergence, revisions unavailable for the `custom_css` post type, wrong
site/theme, malformed response, hash drift, lock-acquisition timeout,
post-write verification failure, a restore refused because the post changed out
of band, revision failure, or validation failure.

The plugin and CLI test suite must cover permission denial, immutable target
selection, unknown-field and size rejection, exact hash matching, a simulated concurrent rail request held against the lock, a
simulated concurrent Customizer save held against the lock, a lock-acquisition
timeout on both paths, idempotent no-op, revision creation, core update
failure, secret redaction, and rollback through the same guarded path.

The capability and recovery behavior need their own cases:

- the `map_meta_cap` filter changes the answer only for the publisher role and
  only for `unfiltered_html`/`edit_css`; another user's `unfiltered_html` check
  and the publisher's other capability checks are unaffected;
- with `DISALLOW_UNFILTERED_HTML` defined true, the filter leaves core's
  `do_not_allow` in place, the preflight fails, and no write is attempted;
- the preflight rejects CSS whose bytes KSES would change — an `&` in a `url()`
  data URI is the cheap fixture — when the grant is absent, and passes the same
  CSS when it is present;
- the preflight rejects content carrying a `</style` sequence or a trailing
  prefix of one, matching `WP_Customize_Custom_CSS_Setting::validate()`;
- post-write verification fails and the re-read hash matches the failed
  read-back: the pre-change content is restored and the restore is verified
  against the pre-change hash;
- post-write verification fails and a simulated out-of-band write changes the
  post first: no restore happens, and both hashes appear in the failure report;
- the restore's own read-back mismatches: the rail stops, reports, and does not
  write again.

Approval enforcement and the Customizer refusal need their own cases too:

- the publisher identity cannot create, edit, or delete an `rt_css_approval`
  record by any route, including the stock REST and admin-post handlers;
- `PUT` is rejected for an unknown, expired, already-consumed, already-failed,
  wrong-stylesheet, or wrong-bytes approval reference, and a body carrying any
  hash field at all is rejected as an unknown field;
- the expected hashes used in the locked comparison come from the record: a
  test that mutates the record and leaves the request identical must change the
  outcome;
- an approval is spent exactly once — a second `PUT` with the same reference
  fails, including when two requests race for the same record;
- a bounded-wait timeout in `customize_validate_custom_css[<stylesheet>]`
  returns a `WP_Error` and the Customizer save is refused with nothing written;
- the lock is released on `customize_save_after` for a publishing save, and on
  `shutdown` for a changeset save that never publishes;
- the acquire-once flag holds: a validate path that runs twice in one request
  acquires once and releases once;
- a write completing in the same second as the previous one still verifies,
  proving the check no longer depends on `post_modified_gmt`;
- with revisions disabled for `custom_css`, preflight refuses and no write is
  attempted.

The plugin package may contain only these three routes, the scoped
`map_meta_cap` filter, the approval record type and its admin screen, the
Customizer lock hooks, and its activation/deactivation role and capability
cleanup. The repository publisher may read only the canonical CSS file
and its CSS-specific secrets. Neither component may change posts, pages,
plugins, templates, navigation, media, users, general options, or other theme
settings.

### Residual risks

The lock and the hook pair cover writes that go through WordPress's own
save paths. They do not cover:

- **Direct SQL or WP-CLI writes.** `wp post update`, a direct database
  console, or any code that writes the `custom_css` post's content without
  going through `wp_update_custom_css_post()` or the Customizer's save action
  bypasses both `GET_LOCK` calls entirely. Nothing in this design prevents
  that; it is out of scope for a plugin-level lock. The recovery re-read
  detects such a write in one narrow window — between the rail's own failed
  read-back and its restore — and refuses to restore over it. That is
  detection in a single window, not coverage.
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
- **The `unfiltered_html` decision is a real widening.** It is scoped to one
  role and one capability and never enters the role's stored capabilities, but
  inside that scope it is exactly as broad as unfiltered HTML on that post's
  content: the publisher can store markup in `custom_css`. The compensating
  controls are the single canonical source file, the reviewed diff, the
  approval binding, and the `</style` markup rule — not KSES.
- **Hosts that disallow unfiltered HTML.** Where `DISALLOW_UNFILTERED_HTML` is
  set, or on multisite where the publisher is not a super admin, the rail
  cannot write byte-exact CSS and refuses to write at all. Publishing shared
  CSS on such an install stays manual until the host's own setting changes,
  which is an owner decision rather than a plugin one.
- **Changeset publishes that skip validation.** A scheduled changeset
  published by WP-Cron, or code transitioning a `customize_changeset` post to
  `publish` directly, reaches `_publish_changeset_values()` without passing
  through `customize_validate_custom_css[<stylesheet>]`. The plugin still
  acquires the lock on `customize_save` there, but that hook cannot refuse a
  save, so contention on that path is recorded rather than prevented.
- **Approval quality is human.** The record proves an administrator approved a
  specific source hash against a specific live hash before a specific expiry.
  It cannot prove they read the diff. Nor does it constrain administrators
  themselves, who can always edit Additional CSS directly; the rail governs the
  publishing identity, not the site's own administrators.
- **The preflight is a prediction.** It runs the filter chain at plan time and
  again inside the lock, but a filter whose behavior depends on time or request
  context can still act differently at write time. Post-write verification is
  the backstop, and its restore path is guarded as described above.
- **A crashed lock holder before its timeout.** `GET_LOCK` releases when its
  MySQL session ends, but a process that dies without ending its own
  connection cleanly (rare, but possible under some hosting/process-manager
  configurations) can hold the lock until the connection is reaped rather
  than until the request's nominal timeout.

## Implementation and rollout gates

Implementation is a separate reviewed task and PR. Rollout order is: plugin
tests → CLI tests against a disposable WordPress fixture → owner review →
plugin install/activation → dedicated user, role, and the scoped capability
grant → `rt_approve_shared_css` on the administrator role → Application
Password → secret installation → a production preflight confirming byte
fidelity and revision availability on the target host → read-only production
`plan` → an approval record minted in wp-admin → one guarded write →
verification. Each production mutation remains
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
- WordPress core `map_meta_cap()`:
  https://developer.wordpress.org/reference/functions/map_meta_cap/
- WordPress `map_meta_cap` filter:
  https://developer.wordpress.org/reference/hooks/map_meta_cap/
- WordPress `user_has_cap` filter (considered and not used):
  https://developer.wordpress.org/reference/hooks/user_has_cap/
- WordPress `WP_User::has_cap()`:
  https://developer.wordpress.org/reference/classes/wp_user/has_cap/
- WordPress core `current_user_can()`:
  https://developer.wordpress.org/reference/functions/current_user_can/
- WordPress core `kses_init()`:
  https://developer.wordpress.org/reference/functions/kses_init/
- WordPress core `kses_init_filters()`:
  https://developer.wordpress.org/reference/functions/kses_init_filters/
- WordPress core `wp_filter_post_kses()`:
  https://developer.wordpress.org/reference/functions/wp_filter_post_kses/
- WordPress core `wp_kses()`:
  https://developer.wordpress.org/reference/functions/wp_kses/
- WordPress core `wp_kses_normalize_entities()`:
  https://developer.wordpress.org/reference/functions/wp_kses_normalize_entities/
- WordPress `update_custom_css_data` filter:
  https://developer.wordpress.org/reference/hooks/update_custom_css_data/
- WordPress core `wp_insert_post()`:
  https://developer.wordpress.org/reference/functions/wp_insert_post/
- WordPress core `wp_update_post()`:
  https://developer.wordpress.org/reference/functions/wp_update_post/
- WordPress core `sanitize_post()`:
  https://developer.wordpress.org/reference/functions/sanitize_post/
- WordPress core `sanitize_post_field()`:
  https://developer.wordpress.org/reference/functions/sanitize_post_field/
- WordPress `{$field_no_prefix}_save_pre` filter, the dynamic hook that is
  `content_save_pre` for post content (there is no separate reference page for
  `content_save_pre` itself):
  https://developer.wordpress.org/reference/hooks/field_no_prefix_save_pre/
- WordPress `pre_{$field}` filter, the dynamic hook that is `pre_post_content`
  for post content:
  https://developer.wordpress.org/reference/hooks/pre_field/
- WordPress core `WP_Customize_Custom_CSS_Setting::validate()`:
  https://developer.wordpress.org/reference/classes/wp_customize_custom_css_setting/validate/
- WordPress core `wp_save_post_revision()`:
  https://developer.wordpress.org/reference/functions/wp_save_post_revision/
- WordPress core `wp_restore_post_revision()` (considered and not used for
  automatic recovery):
  https://developer.wordpress.org/reference/functions/wp_restore_post_revision/
- WordPress core `wp_save_post_revision_on_insert()`:
  https://developer.wordpress.org/reference/functions/wp_save_post_revision_on_insert/
- WordPress core `wp_get_latest_revision_id_and_total_count()`:
  https://developer.wordpress.org/reference/functions/wp_get_latest_revision_id_and_total_count/
- WordPress core `wp_get_post_revisions()`:
  https://developer.wordpress.org/reference/functions/wp_get_post_revisions/
- WordPress core `wp_revisions_enabled()`:
  https://developer.wordpress.org/reference/functions/wp_revisions_enabled/
- WordPress `customize_validate_{$this->ID}` filter:
  https://developer.wordpress.org/reference/hooks/customize_validate_this-id/
- WordPress core `WP_Customize_Setting::validate()`:
  https://developer.wordpress.org/reference/classes/wp_customize_setting/validate/
- WordPress core `WP_Customize_Manager::validate_setting_values()`:
  https://developer.wordpress.org/reference/classes/wp_customize_manager/validate_setting_values/
- WordPress core `WP_Customize_Manager::save_changeset_post()`:
  https://developer.wordpress.org/reference/classes/wp_customize_manager/save_changeset_post/
- WordPress core `WP_Customize_Manager::_publish_changeset_values()`:
  https://developer.wordpress.org/reference/classes/wp_customize_manager/_publish_changeset_values/
- WordPress hook `customize_save_validation_before`:
  https://developer.wordpress.org/reference/hooks/customize_save_validation_before/
- WordPress hook `shutdown`:
  https://developer.wordpress.org/reference/hooks/shutdown/
- WordPress core `register_post_type()`:
  https://developer.wordpress.org/reference/functions/register_post_type/
- WordPress core `wp_slash()` and `wp_unslash()`:
  https://developer.wordpress.org/reference/functions/wp_slash/ and
  https://developer.wordpress.org/reference/functions/wp_unslash/
- MySQL locking functions, including `GET_LOCK` re-entrancy within a session:
  https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html

Capability and filtering behavior above was read from the WordPress 7.1 source
(`wp-includes/theme.php`, `post.php`, `kses.php`, `capabilities.php`,
`customize/class-wp-customize-custom-css-setting.php`), not from recollection.

