# Guarded shared-CSS publishing rail

Status: design only for `rtai-lqcf`. Nothing in this document creates a
credential, registers an endpoint, or changes the public site.

## Decision

Publish only `assets/css/shared.css` through a small Real Treasury WordPress
plugin endpoint. The endpoint calls WordPress core's
`wp_update_custom_css_post($css, ['stylesheet' => get_stylesheet(), 'preprocessed' => ''])`;
it does not write the `custom_css` post directly. This is the same core storage
path used by Appearance → Customize → Additional CSS and preserves WordPress
revisions and the active theme's `custom_css_post_id`.

The `preprocessed` argument is passed explicitly, and empty, because the rail
publishes raw repository CSS and has no preprocessed representation to store.
That argument is not cosmetic: `wp_update_custom_css_post()` writes
`$args['preprocessed']` into the post's `post_content_filtered` column, and its
default is the empty string — so any call that omits it clears whatever was
there. On a site whose Additional CSS is authored through a preprocessor
hooked to `update_custom_css_data` (the documented use for that filter is
storing the pre-processed source in `post_content_filtered` and the compiled
CSS in `post_content`), the first rail publish would destroy the source
representation. The rail therefore treats a non-empty `post_content_filtered`
as a signal that this install's CSS is authored in a way the rail does not
model, and refuses to publish unless an administrator has explicitly approved
clearing it. See "The preprocessed field" below.

The stock posts endpoint is not the rail. `custom_css` is an implementation
post type, and granting a deployment identity general post or theme-editing
authority would make the credential broader than this job. The plugin instead
owns exactly three authenticated routes under `rt-css/v1/shared`:

- `GET` returns the active stylesheet slug, current CSS, SHA-256 of its exact
  UTF-8 bytes, the SHA-256 and byte count of `post_content_filtered` (the
  preprocessed field, normally empty), custom-CSS post ID, and latest revision
  ID. It has no side effects. The preprocessed bytes themselves are not
  exported: the rail never authors them, and the in-lock snapshot is what a
  restore uses.
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

What the credential can still do, because any logged-in user can, is post a
comment through `/wp/v2/comments` on a post with comments open. That is
ordinary subscriber-level behavior and is filtered by KSES like anyone else's —
but it is the reason the capability approach below was abandoned: an
`unfiltered_html` grant would have made that same comment path unfiltered.

The publisher gets no capability grant of any kind beyond those two. Earlier
revisions of this design granted `unfiltered_html` through a scoped
`map_meta_cap` filter; that was wrong, for reasons set out under "Lifting KSES
for one write, not for one user" below, and it is gone. The exception the rail
needs is made around one function call, not around a user.

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
  `content_save_pre` filter to the content. That happens for every post type,
  `custom_css` included — but it is not the end of the write path.
- After sanitization, and before the database write, `wp_insert_post()` does two
  more things to `post_content`. It encodes emoji: for each of
  `post_title`, `post_content`, `post_excerpt` it reads
  `$wpdb->get_col_charset( $wpdb->posts, $field )` and, when that charset is
  `utf8` or its alias `utf8mb3`, runs the value through `wp_encode_emoji()`,
  which replaces characters outside the BMP with HTML entities. Then it applies
  the `wp_insert_post_data` filter to the slashed `$data` array, unslashes the
  result, and writes it with `$wpdb->update()`. Anything hooked to
  `wp_insert_post_data` can rewrite the content after every filter named above
  has already run.
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

### Lifting KSES for one write, not for one user

The previous revision granted the publisher `unfiltered_html` through a
`map_meta_cap` filter scoped to its role. That is not a narrow grant, and the
review that caught it is right.

`kses_init()` does not filter per request or per endpoint. When
`current_user_can( 'unfiltered_html' )` is true it calls `kses_remove_filters()`
and installs nothing, for the whole request — post content, excerpts, titles,
and comments alike. And the publisher is an ordinary authenticated user
elsewhere: `WP_REST_Comments_Controller::create_item_permissions_check()`
requires only that the user be logged in (plus the usual comment-status checks
on the target post); it does not require `edit_posts`. So a role-scoped
`unfiltered_html` grant turns the Application Password into a way to post
unsanitized markup through `/wp/v2/comments`, on a credential whose whole
justification is that it can do exactly one thing.

Narrowing the grant to the handler does not work either, and for a reason worth
recording: `kses_init()` is hooked to `init` and to `set_current_user`, both of
which run before any REST callback. The filter set for the request is decided
by then. A capability that only answered "true" inside the publish handler
would arrive too late to lift anything — the filters would already be
installed. A capability-based grant is therefore either always on, and leaks
into every other request the credential can make, or it is scoped to the
handler and does nothing at all. There is no version of it that is both narrow
and effective.

So the rail does not touch the capability system. Inside the publish handler
only, after the approval record has been validated and while the lock is held,
the plugin calls `kses_remove_filters()` immediately before
`wp_update_custom_css_post()` and restores the filters in a `finally`
immediately after. Both functions are core's own:
`kses_remove_filters()` removes `title_save_pre`, both `pre_comment_content`
variants, and the `content_save_pre` / `excerpt_save_pre` /
`content_filtered_save_pre` filters; `kses_init_filters()` re-adds them.

The restore calls `kses_init()`, not `kses_init_filters()` directly.
`kses_init()` is `kses_remove_filters()` followed by `kses_init_filters()` only
when the current user lacks `unfiltered_html`, which is exactly core's own
decision for whoever is making this request. For the publisher — who has no
such capability now — the two are equivalent; for an administrator who somehow
reached this route, `kses_init_filters()` alone would leave KSES installed for
a user core would not filter. Re-running core's decision is the version that is
right in both cases.

Why this is narrower than the capability route: it applies to one code path, on
one route, for the duration of one function call, only after an
administrator-minted approval has been validated and the lock acquired. Nothing
about the publisher's identity changes, so every other request that credential
makes — comments included — is filtered exactly as any other subscriber-level
user's would be.

**Guardrails.** The lift is refused unless all of these hold at the moment it
happens:

- `current_user_can( 'rt_publish_shared_css' )` is true;
- the request is the rail's own REST route, established from the matched route
  rather than from anything in the request body;
- the approval record has already been validated and the lock is held — the
  lift happens between step 3 and step 4 of the locked sequence, never before;
- the restore is in a `finally`, so an exception or a fatal in the write path
  cannot leave the filters off. Because PHP does not guarantee a `finally` on a
  fatal error, the plugin also registers a `shutdown` callback that restores
  the filters if its own flag says they are still lifted — the same
  belt-and-braces pattern used for the Customizer lock.

The rule that earlier revisions of this document stated as "never unhook KSES"
is therefore restated, because as written it forbade the safest available
design: **never unhook KSES globally, and never by capability; unhook it only
inside the locked, approved write, and re-add it in a `finally`.**

**What the lift still allows.** For the duration of that one call the publisher
can store arbitrary bytes, markup included, in the `custom_css` post's content.
That is the point — it is what byte-exact publishing of a stylesheet requires.
What bounds it is the rest of the rail: one canonical tracked source file, a
reviewed diff, an administrator-minted approval bound to the source hash, the
`</style` markup rule the preflight enforces, and a post-write read-back that
fails the publish if the stored bytes are not the approved bytes.

### Hosts that disallow unfiltered HTML

Because the rail no longer asks the capability system for anything, neither
`DISALLOW_UNFILTERED_HTML` nor multisite's super-admin rule affects whether the
write can produce byte-exact CSS. The multisite condition was an artifact of
the capability route and no longer applies at all.

`DISALLOW_UNFILTERED_HTML` is different: it is an explicit statement by the
host operator that nothing on this install should write unfiltered content
through WordPress. The rail honors it and refuses to publish where it is
defined and true, even though it could technically proceed. Shared CSS stays a
manual task on such an install unless the owner changes the host's own setting.
That is a deliberate choice to treat the constant as an instruction rather than
an obstacle.

### The preprocessed field

`post_content_filtered` on the `custom_css` post is where a CSS preprocessor
plugin keeps its source — Sass or Less text, say — while `post_content` holds
the compiled CSS the site serves. `wp_update_custom_css_post()` overwrites that
column on every call with whatever `preprocessed` argument it was given,
defaulting to an empty string.

The rail has no preprocessed representation. `assets/css/shared.css` is plain
CSS and there is no source it was compiled from. So the design does not attempt
to preserve or regenerate a preprocessed value; it refuses to run where one
exists:

- `GET` reports the field's hash and byte count alongside the content hash.
- The preflight and the in-lock re-read both refuse when
  `post_content_filtered` is non-empty, with one exception: the approval record
  carries a `clear_preprocessed` flag, default false, which only an
  administrator can set when minting the record. Setting it is an explicit
  statement that the preprocessed source is expendable on this install.
- Every snapshot the rail takes — the approval record, the in-lock pre-change
  capture, and the receipt — carries both fields, not just the content. Hashes
  alone would not be enough to restore anything, which is why the pre-change
  snapshot stores the bytes; see "Durable copies of the preprocessed source".
- Restores put both back:
  `wp_update_custom_css_post( $pre_change_css, array( 'stylesheet' => get_stylesheet(), 'preprocessed' => $pre_change_preprocessed ) )`.
  A rollback that restored only `post_content` would leave the clearing in
  place, which is the same data loss one step later.

A preprocessor could also write the field from inside `update_custom_css_data`
during our own write. The preflight applies that filter, so it sees the
resulting `preprocessed` value; a non-empty result is the same signal and gets
the same refusal.

### Durable copies of the preprocessed source

Hashes prove a restore worked; they cannot perform one. Two things follow, and
the previous revision of this design had neither.

**Revisions do not carry the field by default.** `_wp_post_revision_fields()`
returns `post_title`, `post_content`, and `post_excerpt`, so a `custom_css`
revision stores the CSS and not the preprocessed source. The plugin therefore
filters `_wp_post_revision_fields` to add `post_content_filtered` when the post
being revisioned is a `custom_css` post, which makes `_wp_put_post_revision()`
copy the field into every revision the rail saves — and makes
`wp_save_post_revision()`'s change detection notice a change in that field
alone.

The filter must also remove the field for every other post type, not merely
decline to add it: `_wp_post_revision_fields()` caches its list in a `static`
and re-applies the filter to that cached value on each call, so a field added
for one post would otherwise persist for the next post in the same request.

Two limits to state rather than discover later. Revisions saved before this
plugin was active do not carry `post_content_filtered` — for those, only
`post_content` can be restored from the revision. And the filter changes what
core stores for `custom_css` revisions generally, including revisions created
by a Customizer save, which is the intended effect but is a site-wide change
the plugin makes.

**The approval record keeps a full pre-change snapshot.** At publish time,
inside the lock and before the write, the handler stores both fields as content
on the approval record's row — `pre_change_content` and
`pre_change_preprocessed`, alongside the hashes it already keeps. The record's
post type is already `'public' => false` and `'show_in_rest' => false`, and the
snapshot fields are excluded from search and from any REST exposure; they are
readable only by a user holding `rt_approve_shared_css`.

Snapshots are retained for 90 days and then cleared by a scheduled cleanup that
leaves the rest of the record — hashes, commit, approver, state — in place as
the audit row. Ninety days is a judgment call, not a derived number: long
enough that a preprocessed source cleared by mistake is still recoverable after
a quiet period, short enough that the site is not holding stylesheet copies
indefinitely.

**Restore order.** The recovery path reads the snapshot on the approval record
first, because it is the only copy guaranteed to hold both fields, and falls
back to the revision named by `pre_change_revision_id` when the snapshot has
aged out. A fallback restore can only put back what the revision holds, and the
failure report says which source was used.

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
`rt-css-publisher` role does not hold `rt_approve_shared_css`, and nothing in
the plugin grants it at runtime.

**What is stored.** Each approval is one record in a private custom post type,
`rt_css_approval`, registered with `'public' => false`,
`'show_in_rest' => false`, and its create, edit, and delete capabilities all
mapped to `rt_approve_shared_css`:

| Field | Meaning |
| --- | --- |
| `source_sha256` | SHA-256 of the exact bytes approved for publication |
| `expected_live_sha256` | SHA-256 of the live CSS the approver saw, read by the server, not supplied |
| `expected_live_filtered_sha256` | SHA-256 of `post_content_filtered` as the approver saw it, also read by the server |
| `clear_preprocessed` | Default false. True only if an administrator explicitly approved clearing a non-empty `post_content_filtered` |
| `pre_change_content` | The live `post_content` bytes captured in the lock immediately before the write; retained 90 days |
| `pre_change_preprocessed` | The live `post_content_filtered` bytes captured at the same moment; retained 90 days |
| `source_commit` | Repository commit those bytes came from |
| `stylesheet` | Stylesheet slug this approval is valid for |
| `approver_user_id` | The administrator who minted it |
| `created_gmt` | Mint time |
| `expires_gmt` | Mint time plus a short TTL, 30 minutes by default and capped by the plugin |
| `state` | `open`, `consumed`, or `failed` — single use in every case |

The minting screen reads both live hashes itself rather than accepting them.
The approver supplies the source hash and commit from the `plan` output after
reviewing the diff, and must tick `clear_preprocessed` deliberately if the
preprocessed field is not empty — the screen shows its byte count when it is
not. The screen refuses to mint a record whose `source_sha256`
already equals the live hash, since there would be nothing to publish, and
refuses when the stylesheet is not the install's recorded Astra slug.

**What `PUT` does with it.** The request carries `css` and `approval_ref` and
nothing else. The handler loads the record and refuses unless the record
exists, its `state` is `open`, `expires_gmt` is in the future, its `stylesheet`
matches the active stylesheet, and the SHA-256 of the submitted CSS equals the
record's `source_sha256`. The expected live hashes used in the locked
comparison, for both `post_content` and `post_content_filtered`, are read from
the record. No hash in the request body is trusted, because there
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

The `preflight` route asks one question — if this exact string were written
now, by this user, on this install, would the stored `post_content` be these
same bytes? — by running the candidate through as much of the write path as can
be reproduced without writing. It is an early detector, not a proof; the
authoritative check is the post-write read-back described later. What it covers:

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
4. the rest of what `wp_insert_post()` does before the database write: the
   emoji encoding, by reading
   `$wpdb->get_col_charset( $wpdb->posts, 'post_content' )`, and the
   `wp_insert_post_data` filter, applied to a `$data` array of the same shape
   `wp_insert_post()` builds. Applying that filter outside core is an
   approximation — core passes its own internally derived `$postarr` and
   `$unsanitized_postarr` alongside `$data`, and a filter that reads those may
   behave differently here than it will on the real write. The document says
   approximation rather than equivalence on purpose;
5. the storage charset itself: the rail refuses unless
   `get_col_charset( $wpdb->posts, 'post_content' )` is `utf8mb4`. On `utf8` or
   `utf8mb3`, emoji are entity-encoded on the way in, which changes the bytes,
   and any other non-BMP character cannot round-trip through a three-byte
   column at all. Refusing outright is simpler than reasoning per-character
   about a stylesheet;
6. the preprocessed field: a non-empty `post_content_filtered`, or an
   `update_custom_css_data` filter that produces a non-empty `preprocessed`
   value, refuses unless the approval record sets `clear_preprocessed`;
7. the preconditions the rest of the rail depends on: that
   `DISALLOW_UNFILTERED_HTML` is not set on this host, that revisions are
   enabled for the `custom_css` post type, and that the plugin's
   `_wp_post_revision_fields` filter is installed — the rollback artifact, the
   preprocessed copy, and the post-write version signal all depend on them.

If the result differs from the input by one byte, `preflight` fails, names the
stage that changed it and the first differing offset, and `plan` refuses to
produce an approvable diff. The same check runs again inside the lock,
immediately before the write, against the bytes actually about to be written:
capability state, an object cache, or a plugin update can change between `plan`
and `apply`.

**What the preflight does not establish.** It is a best-effort early detector.
It cannot be a proof, for reasons that are structural rather than fixable:
`wp_insert_post()` builds `$postarr` and `$unsanitized_postarr` internally and
passes them to `wp_insert_post_data`, so a filter reading those arguments can
behave differently under simulation; a filter whose behavior depends on time,
request context, or state that changes between the check and the write can
diverge; and the preflight executes whatever third-party code is hooked
anywhere on that chain, so it is read-only in what it does, not necessarily in
what other code hooked there does.

The authoritative check is therefore the post-write read-back, not the
preflight. The design guarantees exactly one thing about byte fidelity: after
the write, the stored `post_content` is read back and compared to the approved
source hash, and a mismatch — whatever caused it, filtering included — fails
the publish and triggers the guarded restore. The preflight exists to catch the
common cases before anything is written, not to make that comparison
redundant.

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
   from the CLI's own prior read). Hold both `post_content` and
   `post_content_filtered` in request memory as the pre-change snapshot, and
   record both hashes.
2. Compare the live content hash to the record's `expected_live_sha256`, the
   live `post_content_filtered` hash to its `expected_live_filtered_sha256`,
   and the SHA-256 of the submitted CSS to its `source_sha256`. Every expected
   value comes from the record; none comes from the request. Refuse if
   `post_content_filtered` is non-empty and the record does not set
   `clear_preprocessed`. Any mismatch or refusal aborts with no write and
   releases the lock.
3. Re-run the preflight against the bytes about to be written. If it reports
   that any byte would change, that `DISALLOW_UNFILTERED_HTML` is set, that the
   `post_content` column is not `utf8mb4`, or that revisions are unavailable,
   abort with no write and release the lock.
4. Save the current custom-CSS post as a revision with `wp_save_post_revision()`
   and record its ID as `pre_change_revision_id` (see "Revision, audit, and
   rollback" below). A null return here is not automatically a failure:
   `wp_save_post_revision()` skips when the latest revision already holds
   exactly this content, which is the normal state right after a previous rail
   write. In that case record the existing latest revision ID instead; treat
   null as a failure only when no revision of the current content exists.
   Then — after that revision exists, not before — read the post's latest
   revision ID from `wp_get_latest_revision_id_and_total_count()` and record it
   as `pre_write_revision_id`. Write the pre-change snapshot — both fields as
   content, not hashes — onto the approval record. Then lift KSES with
   `kses_remove_filters()` under the guardrails above, call
   `wp_update_custom_css_post($css, ['stylesheet' => get_stylesheet(), 'preprocessed' => ''])`,
   and restore the filters with `kses_init()` in a `finally` that wraps only
   that call.
5. Read the post back, again with `wp_get_custom_css_post()`. Require the
   stored CSS hash to equal the record's `source_sha256`, the stored
   `post_content_filtered` to match what the preflight predicted (empty, in
   every case the rail permits), and the post's latest revision ID to be
   greater than `pre_write_revision_id`. Any failure means the write did not
   land as intended: record the hashes just observed and go to "When post-write
   verification fails" below, still holding the lock.
6. Mark the approval record spent — `consumed` on a verified write, `failed`
   otherwise — while the lock is still held.
7. Release `GET_LOCK` in the `finally` block, on every branch.

Holding the lock across all seven steps is what makes step 2's comparison mean
anything: without it, a second writer could still slip in between the compare
and the write, which is exactly the gap the original compare-and-set design
left open. Step 3 raises the odds that a write which reaches step 4 will
succeed, but it does not make step 5 redundant: step 5 is the authoritative
check, and a mismatch there is treated as a failed publish whatever caused it.

The version signal in step 5 is the revision ID, not `post_modified_gmt`. Post
modified timestamps have second precision and are not a monotonic counter: a
write completing in the same second as the previous one can store an identical
`post_modified_gmt`, so requiring it to advance would fail valid publishes and
trigger a pointless restore. Revision IDs are `wp_posts.ID` values from an
auto-increment column, so a newer revision always carries a larger ID. Core
produces one for us — `wp_save_post_revision_on_insert()` is hooked to
`wp_after_insert_post`, and `wp_save_post_revision()` on `post_updated` defers
to it — so the update itself stores a revision holding the new content, on top
of the pre-change revision the handler saved explicitly in step 4.

The comparison point has to be read after that pre-change revision exists. An
earlier draft recorded the latest revision ID in step 1 and compared against it
in step 5, which the handler's own step-4 revision already exceeded: the check
would have passed whether or not the write produced anything. `pre_write_revision_id`
is therefore read immediately before the call into
`wp_update_custom_css_post()`, and the post-write latest ID must exceed that. Because the
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
`wp_get_custom_css_post()` and hashes both fields. The re-read content hash
must equal the hash observed in the failed read-back in step 5 — the state the
rail's own write produced. Only then is restoring safe. If it differs,
something wrote to the post outside the lock in the interval between our
read-back and now: a direct SQL update or a WP-CLI invocation, the two paths
the lock cannot cover.
Restoring would silently destroy that writer's content, so the rail does not
restore.

The restore reads the pre-change snapshot from the approval record — the only
copy guaranteed to hold both fields — and falls back to the revision named by
`pre_change_revision_id` if that snapshot has aged out of retention, in which
case it can only restore what the revision holds and the report says so. It
then re-writes that snapshot — both fields — through
`wp_update_custom_css_post( $pre_change_css, array( 'stylesheet' => get_stylesheet(), 'preprocessed' => $pre_change_preprocessed ) )`,
rather than calling `wp_restore_post_revision()` on the recorded revision ID.
Passing `preprocessed` explicitly is not optional here: omitting it would leave
`post_content_filtered` cleared, which is the data loss the restore is supposed
to undo. Either approach would put the old CSS back; this one is chosen
because:

- it is the path the rail already preflights and verifies, so the restore gets
  the same treatment as a publish, including a read-back whose expected values —
  the pre-change hashes recorded in step 1, for both fields — were known before
  the write rather than read out of the row being repaired;
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
| Read-back content hash equals the record's `source_sha256`, `post_content_filtered` matches the preflight's prediction, and the latest revision ID is greater than `pre_write_revision_id` | Success. Mark the approval `consumed`, release the lock, return the receipt. |
| Verification failed, and the re-read content hash equals the hash observed in the failed read-back | Restore both pre-change fields from the approval record's snapshot (falling back to `pre_change_revision_id` if it has aged out) through `wp_update_custom_css_post()` with the captured `preprocessed` value; read back and require both stored hashes to equal the pre-change hashes from step 1; mark the approval `failed`; release the lock; report the publish as failed, the restore as verified, and which source it came from. |
| Verification failed, and the re-read content hash differs from the hash observed in the failed read-back | Do not restore. Mark the approval `failed`, release the lock, and fail loudly, recording the approved source hash, the hash observed in the read-back, the differing re-read hash, both pre-change hashes, `pre_change_revision_id`, and `pre_write_revision_id`. An out-of-band writer touched the post; what to keep is a human decision. |
| The restore's own read-back does not match the pre-change hashes, either field | Stop. Attempt nothing further automatically. Mark the approval `failed`, release the lock, and report every hash observed, the restore source used, and `pre_change_revision_id`, `pre_write_revision_id`, and the post-write revision ID. The site's CSS is in a known-bad state, the snapshot on the approval record is the copy to recover from, and the next step is manual. |

In every row the approval is spent, the lock is released exactly once in the
`finally` block, and no row retries a write automatically. A retry after any
failure needs a new approval minted by an administrator.

## Revision, audit, and rollback

Before changing CSS, the plugin explicitly saves the current custom-CSS post as
a revision — carrying `post_content_filtered` as well as `post_content`,
because the plugin's `_wp_post_revision_fields` filter is installed — records
its ID as `pre_change_revision_id`, reads the post's latest
revision ID once that revision exists as `pre_write_revision_id`, and records
the SHA-256 of both the pre-change `post_content` and the pre-change
`post_content_filtered` it just read — those hashes are what a restore is
verified against.
A successful response and an owner-only local receipt contain: timestamp, actor
user ID, target origin, stylesheet, approval reference, approver user ID,
source commit, old/new SHA-256 for `post_content`, old/new SHA-256 for
`post_content_filtered`, whether `clear_preprocessed` was set,
`pre_change_revision_id`, `pre_write_revision_id`, the post-write latest
revision ID, resulting post ID, whether a pre-change snapshot was stored on the
approval record and when it expires, the restore source if a restore ran, and
the approval record's resulting state.
A failed publish records the same fields plus every hash named in the recovery
table, and which row of that table was taken. CSS and credentials are not
copied into the receipt.

Rollback uses the same guarded `PUT`, not a broader restoration endpoint: read
the revision named by `pre_change_revision_id` in wp-admin, save its exact CSS
to a temporary owner-only file, plan it against the current live hash — which runs the same
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
divergence, a non-empty `post_content_filtered` without an explicit
`clear_preprocessed` approval, a host with `DISALLOW_UNFILTERED_HTML` set, a
`post_content` column that is not `utf8mb4`,
revisions unavailable for the `custom_css` post type, wrong site/theme,
malformed response, hash drift, lock-acquisition timeout, post-write
verification failure, a restore refused because the post changed out
of band, revision failure, or validation failure.

The plugin and CLI test suite must cover permission denial, immutable target
selection, unknown-field and size rejection, exact hash matching, a simulated
concurrent rail request held against the lock, a simulated concurrent
Customizer save held against the lock, a lock-acquisition
timeout on both paths, idempotent no-op, revision creation, core update
failure, secret redaction, and rollback through the same guarded path.

The KSES lift and the recovery behavior need their own cases:

- `current_user_can( 'unfiltered_html' )` is false for the publisher on every
  request, including inside the publish handler;
- **the comment test**: a `POST` to `/wp/v2/comments` authenticated with the
  publisher's Application Password, carrying `<script>` in the comment body,
  must come back with the markup stripped. That is the regression this design
  revision exists to prevent, and it runs against a build where a publish has
  already happened in the same test session;
- the filters are lifted only between the approval check and the write:
  assertions before the lift, during the write, and after the `finally` show
  `has_filter( 'content_save_pre', 'wp_filter_post_kses' )` off only in the
  middle window;
- an exception thrown inside `wp_update_custom_css_post()` still leaves the
  filters restored, and the `shutdown` fallback restores them if the `finally`
  is bypassed;
- the lift refuses when `current_user_can( 'rt_publish_shared_css' )` is false,
  when the request did not match the rail's route, or when the lock is not
  held;
- with `DISALLOW_UNFILTERED_HTML` defined true, the preflight refuses and no
  write is attempted;
- the preflight rejects CSS whose bytes KSES would change — an `&` in a `url()`
  data URI is the cheap fixture — when the lift is not in effect, and the same
  CSS is stored byte-for-byte through the rail's own write;
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

The preprocessed field, the rest of the write path, and the version comparison
need cases of their own:

- a fixture with non-empty `post_content_filtered` is refused by both preflight
  and the in-lock re-read, and is published only when the approval record sets
  `clear_preprocessed`;
- when it is set, the write clears the field, and the receipt carries the
  pre-change filtered hash;
- a restore after a failed write puts both fields back, verified against both
  pre-change hashes — a restore that omits `preprocessed` must fail the test;
- a full round trip on a fixture whose `post_content_filtered` is non-empty:
  publish with `clear_preprocessed` set, fail verification, restore, and assert
  the preprocessed bytes are back exactly;
- the same round trip with the approval snapshot removed, proving the revision
  fallback works and that the report names the fallback as the source used;
- a `custom_css` revision saved with the plugin active carries
  `post_content_filtered`, and a revision of an ordinary post does not — the
  `static` cache inside `_wp_post_revision_fields()` makes the second assertion
  the one that catches a filter which only adds and never removes;
- an `update_custom_css_data` filter returning a non-empty `preprocessed` value
  is detected by preflight and refused;
- a filter hooked to `wp_insert_post_data` that rewrites `post_content` is
  caught by preflight where it can be, and by the post-write read-back in every
  case — the read-back test must not be skipped when preflight already passes;
- `get_col_charset()` reporting `utf8` or `utf8mb3` refuses before any write;
- the version comparison fails when the write produces no new revision: a
  fixture that saves a pre-change revision and then short-circuits the update
  must be caught, which the earlier step-1 comparison would have passed.

The plugin package may contain only these three routes, the scoped KSES lift
inside the publish handler, the `_wp_post_revision_fields` filter for
`custom_css`, the approval record type and its admin screen, the Customizer
lock hooks, and its activation/deactivation role and capability cleanup. The
repository publisher may read only the canonical CSS file and its CSS-specific
secrets. Neither component may change posts, pages,
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
- **The KSES lift is still a lift.** For the duration of one call, with the
  lock held and an approval validated, this plugin turns off WordPress's input
  sanitization for the whole request context. Nothing else in the request is
  supposed to write during that window, but nothing structurally prevents a
  filter hooked to `update_custom_css_data` or `wp_insert_post_data` from
  writing another post while the filters are down. The window is one function
  call wide, the code path is one route, and the restore is in a `finally` with
  a `shutdown` backstop — but the risk is a narrow window, not zero.
- **Hosts that disallow unfiltered HTML.** Where `DISALLOW_UNFILTERED_HTML` is
  defined and true, the rail refuses to publish even though the lift would
  technically work, because that constant is the operator's instruction.
  Publishing shared CSS on such an install stays manual until the owner changes
  the host's own setting.
- **Changeset publishes that skip validation.** A scheduled changeset
  published by WP-Cron, or code transitioning a `customize_changeset` post to
  `publish` directly, reaches `_publish_changeset_values()` without passing
  through `customize_validate_custom_css[<stylesheet>]`. The plugin still
  acquires the lock on `customize_save` there, but that hook cannot refuse a
  save, so contention on that path is recorded rather than prevented.
- **Preprocessed CSS is out of scope, not supported.** The rail refuses on an
  install whose `post_content_filtered` is non-empty unless an administrator
  says to clear it. It does not compile, preserve, or regenerate a preprocessed
  source. After a `clear_preprocessed` publish the copies that exist are the
  pre-change revision — which carries the field only because the plugin's
  `_wp_post_revision_fields` filter is installed — and the retained snapshot on
  the approval record, which ages out. Revisions taken before the plugin was
  active do not carry the field at all. Adopting a preprocessor on this site
  later means revisiting this design, not working around it.
- **The preflight cannot model the write exactly.** `wp_insert_post()` builds
  the `$postarr` and `$unsanitized_postarr` arguments it passes to
  `wp_insert_post_data` internally, so simulating that filter is an
  approximation; filters that depend on time or request context can diverge
  too. The post-write read-back, not the preflight, is what the design
  guarantees.
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
plugin install/activation → dedicated user and role, with no capability grant
of any kind → `rt_approve_shared_css` on the administrator role → Application
Password → secret installation → a production preflight confirming byte
fidelity, revision availability, and that the comment path is still filtered
for the publisher → read-only production `plan` → an approval record minted in
wp-admin → one guarded write → verification. Each production mutation remains
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
- WordPress `WP_User::has_cap()`:
  https://developer.wordpress.org/reference/classes/wp_user/has_cap/
- WordPress core `current_user_can()`:
  https://developer.wordpress.org/reference/functions/current_user_can/
- WordPress core `kses_init()`:
  https://developer.wordpress.org/reference/functions/kses_init/
- WordPress core `kses_init_filters()`:
  https://developer.wordpress.org/reference/functions/kses_init_filters/
- WordPress core `kses_remove_filters()`:
  https://developer.wordpress.org/reference/functions/kses_remove_filters/
- WordPress core `wp_filter_kses()`:
  https://developer.wordpress.org/reference/functions/wp_filter_kses/
- WordPress core `WP_REST_Comments_Controller::create_item_permissions_check()`:
  https://developer.wordpress.org/reference/classes/wp_rest_comments_controller/create_item_permissions_check/
- WordPress core `_wp_post_revision_fields()`:
  https://developer.wordpress.org/reference/functions/_wp_post_revision_fields/
- WordPress `_wp_post_revision_fields` filter:
  https://developer.wordpress.org/reference/hooks/_wp_post_revision_fields/
- WordPress core `_wp_put_post_revision()`:
  https://developer.wordpress.org/reference/functions/_wp_put_post_revision/
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
- WordPress `wp_insert_post_data` filter:
  https://developer.wordpress.org/reference/hooks/wp_insert_post_data/
- WordPress core `wpdb::get_col_charset()`:
  https://developer.wordpress.org/reference/classes/wpdb/get_col_charset/
- WordPress core `wp_encode_emoji()`:
  https://developer.wordpress.org/reference/functions/wp_encode_emoji/
- MySQL locking functions, including `GET_LOCK` re-entrancy within a session:
  https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html

Capability and filtering behavior above was read from the WordPress 7.1 source
(`wp-includes/theme.php`, `post.php`, `kses.php`, `capabilities.php`,
`customize/class-wp-customize-custom-css-setting.php`), not from recollection.

