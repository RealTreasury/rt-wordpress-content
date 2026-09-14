# Guarded shared-CSS publishing rail

Status: design only for `rtai-lqcf`. Nothing in this document creates a
credential, registers an endpoint, or changes the public site. Implementation
is a separate task with its own review and explicit production approval.

An earlier revision of this design ran to about 13,600 words and specified a
plugin that hooked the Customizer, deferred core cron publishes, and rewrote
changeset statuses to serialize every writer of Additional CSS. That was out
of proportion to the job, which is publishing one stylesheet from Git on a
site edited by two people. This revision keeps the guards the task requires,
states the remaining risks plainly with a bound, and leaves the rest to the
implementation task. The long version is in this branch's history at
`b721f6f` for anyone who wants the detail.

## Problem

`assets/css/shared.css` is version controlled here but reaches the site only
by a person pasting it into Appearance → Customize → Additional CSS. The
WordPress.com MCP cannot write the `custom_css` post, and the global-styles
route was tried and rolled back. The owner wants an agent to be able to publish
styling fixes from the repository, CSS only, with a human approving each
production change, and with no automation that can touch anything else.

## Decision

Publish only `assets/css/shared.css`, through a small Real Treasury plugin
that exposes two authenticated REST routes and calls WordPress core's
`wp_update_custom_css_post()`. That is the same storage path the Customizer
uses, so revisions and the theme's `custom_css_post_id` keep working.

Not chosen:

- **Stock `posts` REST endpoint.** `custom_css` is an implementation post
  type. Any credential that can write it there can write other posts too.
- **Global styles / theme.json.** Proven ineffective for these styles; rolled
  back before this task.
- **SFTP or SSH to the host.** Those credentials are whole-site credentials.
  The task asks for a credential that can do one thing.
- **Enqueueing `shared.css` from the child theme directory.** The repository
  copy of `functions.php` still contains an enqueue for it, but the live site
  does not load it that way. Reviving it would mean deploying theme PHP, which
  is a wider change than this task.

## Routes

Both routes live under `rt-css/v1/shared`, require a `permission_callback`
that checks one plugin-defined capability, `rt_publish_shared_css`, and refuse
unless the active stylesheet is the installation's recorded Astra stylesheet
slug. Neither route accepts a stylesheet, post ID, option, path, URL, or
operation from the caller.

**`GET`** returns, with no side effects and no CSS body accepted:

- active stylesheet slug and custom-CSS post ID, or `live_absent: true` when
  no `custom_css` post exists yet
- SHA-256 and byte count of `post_content` (the live CSS)
- SHA-256 and byte count of `post_content_filtered` (the preprocessed field,
  normally empty)
- latest revision ID
- install preconditions: revisions enabled for `custom_css`, `post_content`
  column charset, whether `DISALLOW_UNFILTERED_HTML` is defined, whether
  `GET_LOCK` is available, single-site or multisite
- given an `approval_ref`, whether that record is open, unexpired, and bound
  to this stylesheet

**`PUT`** accepts exactly two fields, `css` and `approval_ref`. Every hash the
write is checked against comes from the approval record, never from the
request. Unknown fields, a non-string CSS value, invalid UTF-8, a body over
512 KiB, a missing or unknown reference, or CSS whose hash does not match the
record are rejected before anything is read from the database.

## Credential

A dedicated WordPress user, `rt-css-publisher`, holding a custom role whose
only capability is `rt_publish_shared_css`. No `edit_posts`, no
`edit_theme_options`, no `unfiltered_html`. Authentication is an Application
Password for that user, stored through the rt-ai render flow (see
`docs/SECRETS.md` in `rt-ai`), never hand-created.

The credential can therefore call the two routes above and nothing else that
does anything. Every other REST route either requires a capability it lacks or
is read-only public data.

### Why the credential does not get `unfiltered_html`

WordPress filters post content through KSES for any user lacking
`unfiltered_html`. `wp_update_custom_css_post()` itself does no capability
check and no sanitization; filtering happens inside `wp_insert_post()` via
`sanitize_post( ..., 'db' )` and the `content_save_pre` filter, where
`kses_init()` has registered `wp_filter_post_kses()` for such users. CSS with
`>`, `&`, or quoted strings would be altered on the way in.

Granting the role `unfiltered_html`, even through a `map_meta_cap` filter
scoped to this post type, does not work as a narrow grant. `kses_init()` runs
on `init` and `set_current_user`, before any REST callback, and installs no
filters at all when `current_user_can( 'unfiltered_html' )`. The publisher
would be unfiltered for every request, including the REST comments endpoint,
which needs only a logged-in user.

So the plugin lifts KSES for the write only: inside the `PUT` handler, after
the approval record is validated and the lock is held, it calls
`kses_remove_filters()`, performs the write, and calls `kses_init()` in a
`finally`. A `shutdown` hook re-runs `kses_init()` as a backstop. The
publisher credential triggers this lift through exactly one path, the approved
guarded write, and through nothing else it can reach.

### Hosts where the lift is not allowed

The rail runs on single-site installs only and refuses on multisite. It also
refuses when `DISALLOW_UNFILTERED_HTML` is defined. Both are checked in `GET`
and again inside the lock. This is one rule, stated once: if either condition
holds, the rail is not deployable on that host and the paste procedure stays.

## Approval is a server-side record

An administrator mints an approval in wp-admin (or via WP-CLI as an
administrator; the CLI helper can compute hashes but cannot approve). The
record is a private custom post type, `rt_css_approval`, whose capabilities
map to `rt_approve_shared_css`, held only by the administrator role. It is
excluded from REST and search.

Each record carries:

- stylesheet slug
- `expected_live_sha256` and `expected_filtered_sha256`, read from the live
  post at mint time
- `source_sha256` and the Git commit of `shared.css`
- `proposed_sha256`, the hash of the bytes the write should produce
- a full snapshot of both live fields at mint time, retained 90 days
- `clear_preprocessed: false` by default; only an administrator can set it,
  and only when they have decided the non-empty preprocessed source should be
  discarded
- single-use status and a TTL (default 30 minutes)

The `PUT` handler marks the record consumed or failed inside the lock. A
second `PUT` with the same reference is refused.

## Publish sequence

1. **Plan** (`shared_css_publish.py plan`, read-only). Requires a clean
   checkout and the canonical tracked path, rejects symlinks and path
   overrides. Calls `GET`, hashes source and live bytes, checks the
   preconditions, and prints a unified diff with stylesheet, source commit,
   source and live hashes, byte counts, and proposed hash. Writes no WordPress
   state and lifts no filters. Refuses if `post_content_filtered` is non-empty
   and no administrator has set `clear_preprocessed`.
2. **Approve.** An administrator reads the diff and mints the approval record.
   The record binds the exact live hash the diff was computed against.
3. **Publish** (`shared_css_publish.py publish --approval <ref>`). Sends
   `PUT` with the CSS and the reference. Inside the handler, all under one
   lock:
   1. validate the record: open, unexpired, bound to this stylesheet, hash of
      the request body equals `proposed_sha256`
   2. re-read the live post; refuse unless both field hashes equal the
      record's expected hashes (drift refusal)
   3. save a pre-change revision and record its ID as `pre_write_revision_id`
   4. lift KSES, call `wp_update_custom_css_post( $css, [ 'stylesheet' =>
      $slug, 'preprocessed' => $preprocessed ] )`, restore KSES
   5. read back both fields; require `post_content` hash equal to
      `proposed_sha256`, `post_content_filtered` hash equal to what was
      intended, and the latest revision ID greater than `pre_write_revision_id`
   6. on any read-back mismatch, restore the snapshot through the same
      guarded write and mark the record failed; the response names which
      check failed and whether the restore succeeded
   7. mark the record consumed and return a receipt: pre- and post-write
      hashes, revision IDs, the approval reference
4. **Verify** (`shared_css_publish.py verify`). Fetches three representative
   pages (home, a webinar landing page, a gated-content page) with a
   cache-busting query string, confirms the `wp-custom-css` style block hashes
   to `proposed_sha256`, and captures phone, tablet, and desktop screenshots
   with the existing `~/tools/webshot.py` for a person to look at. The rail
   does not judge the screenshots.

### The `preprocessed` argument

`wp_update_custom_css_post()` writes its `preprocessed` argument into
`post_content_filtered` and defaults it to the empty string. A call that omits
it clears whatever was there. The rail always passes it explicitly: the
current value when `clear_preprocessed` is false (the write must therefore
refuse when that value is non-empty, since the rail has no representation for
it), or the empty string when an administrator has set the flag.

### First publish on an install with no custom-CSS post

`GET` reports `live_absent: true`. The approval record stores an `absent`
sentinel instead of a live hash and no snapshot. The handler re-checks absence
inside the lock, writes, and verifies that the post now exists, that the
theme's `custom_css_post_id` points at it, and that the hash matches. Rollback
of a first publish writes empty content rather than deleting the post.

## The lock

The handler takes a MySQL advisory lock for the duration of steps 3.1 to 3.7,
with a bounded wait of 5 seconds. The lock name is derived per install,
`rt_css_` followed by the first 16 hex characters of SHA-256 over the database
name, table prefix, and site URL, so two installs sharing a MySQL server cannot
collide. `GET_LOCK` availability is probed at plugin activation and reported by
`GET`; when it is unavailable the rail refuses to publish and there is no
fallback lock. An option-based mutex was considered and rejected: a fixed TTL
reclaims while the holder still executes, and without an ownership token a
release can delete someone else's lock.

The lock serializes rail publishes against each other. It does not serialize
the Customizer, and this design does not hook the Customizer. See the first
residual risk.

## Rollback

Two copies of the prior state exist after every publish: the WordPress
revision saved in step 3.3, and the two-field snapshot on the approval record.
Core revisions carry `post_content` but not `post_content_filtered`, so the
plugin adds a `_wp_post_revision_fields` filter that includes the filtered
field for `custom_css` only (and removes it for other post types, because the
function caches its field list in a static).

`shared_css_publish.py rollback --approval <ref>` is the same guarded write in
reverse: an administrator mints a new approval whose proposed bytes are the
snapshot from the earlier record, and the publish sequence runs unchanged.
There is no separate restore code path, so a restore gets the same drift
check, KSES handling, and read-back verification as a publish.
`wp_restore_post_revision()` is not used because it does not carry the
filtered field and does not go through the compare.

## Failure states

| State | Meaning | Live CSS |
|---|---|---|
| refused | a precondition or hash check failed before the write | unchanged |
| written | write and read-back both succeeded | new |
| restored | read-back failed, snapshot restore succeeded | prior |
| inconsistent | read-back failed and the restore also failed | unknown; receipt has both hashes; human fixes via Customizer revisions |

The publish command exits non-zero for anything but `written`, and prints the
receipt in every case.

## Residual risks

- **Customizer write between step 3.2 and step 3.4.** WordPress has no
  conditional-write primitive for this post, so a Customizer save landing in
  that window would be overwritten. The window is a few milliseconds of one
  request. The only other writers of Additional CSS on this site are the two
  principals, both of whom would be using this rail once it exists. If it
  happens anyway, the overwritten save survives as a WordPress revision and
  the rail's own pre-change revision shows what it replaced. Accepted.
- **Hooked third-party code.** `update_custom_css_data`, `content_save_pre`,
  and `wp_insert_post_data` run during the write with KSES lifted. Anything a
  plugin has hooked there runs unfiltered for that window. Mitigation: the
  rollout audits what is hooked to those three filters before activation, and
  the handler logs the callbacks it observed on each write.
- **Writes that bypass the rail.** Direct SQL, `wp post update`, or a plugin
  writing the post do not consult the lock or the approval record. The next
  `plan` shows the drift and refuses.
- **Host lock support.** Some managed hosts proxy MySQL in ways that break
  `GET_LOCK` or route consecutive requests to different primaries. Confirming
  this on the production host is the first rollout gate.
- **Verification is byte-level, not visual.** The rail confirms the CSS
  arrived. Whether it looks right is a person's call on the screenshots.

## What the rail cannot do

It cannot change posts, pages, media, plugins, templates, template parts,
navigation, options, users, or the theme header. It cannot publish any file
but `assets/css/shared.css`, cannot write to any stylesheet but the active one,
and cannot write without an approval record minted by an administrator in the
last 30 minutes.

## Implementation and rollout gates

In order, each gated by a person:

1. Confirm the production host supports `GET_LOCK` and reaches a stable
   primary. Without this the design is not deployable.
2. Plugin and CLI tests against a disposable WordPress fixture: KSES lifted
   only inside the approved write; comments endpoint still filtered for the
   publisher; drift refusal; first-publish branch; rollback through the same
   primitive; `preprocessed` preserved and cleared paths.
3. Owner review of the implementation PR.
4. Plugin install and activation. Dedicated user and role, with only
   `rt_publish_shared_css`. `rt_approve_shared_css` added to the administrator
   role. Application Password created and stored through the render flow.
5. Audit of what is hooked to the three write-path filters.
6. Read-only production `plan`, an approval, one guarded write, `verify`.

## Verified WordPress behavior this design relies on

Checked against WordPress 7.1 source during review of this design.

- `wp_update_custom_css_post()` does no capability check and no
  sanitization; it passes `post_content_filtered => $args['preprocessed']`
  with a default of `''`.
- Filtering happens in `wp_insert_post()` → `sanitize_post( ..., 'db' )` →
  `content_save_pre`, where `kses_init()` registers `wp_filter_post_kses()`
  for users lacking `unfiltered_html`.
- `kses_init()` runs on `init` and `set_current_user` and installs no filters
  when `current_user_can( 'unfiltered_html' )`, so a capability grant
  unfilters the whole request.
- There is no `unfiltered_css` capability; `edit_css` is a meta capability
  mapped in the same `map_meta_cap()` case as `unfiltered_html`.
- `wp_insert_post()` also runs `wp_encode_emoji()` when the `post_content`
  column charset is `utf8` or `utf8mb3`; the rail requires `utf8mb4`.
- Core revisions store `post_content` and not `post_content_filtered`;
  `_wp_post_revision_fields()` caches its field list in a static.
- MySQL named locks are server-wide, not namespaced per database.

## Primary references

- `wp_update_custom_css_post()`:
  https://developer.wordpress.org/reference/functions/wp_update_custom_css_post/
- `wp_get_custom_css_post()`:
  https://developer.wordpress.org/reference/functions/wp_get_custom_css_post/
- `update_custom_css_data` filter:
  https://developer.wordpress.org/reference/hooks/update_custom_css_data/
- `wp_insert_post()`:
  https://developer.wordpress.org/reference/functions/wp_insert_post/
- `sanitize_post()`:
  https://developer.wordpress.org/reference/functions/sanitize_post/
- `kses_init()`, `kses_init_filters()`, `kses_remove_filters()`,
  `wp_filter_post_kses()`:
  https://developer.wordpress.org/reference/functions/kses_init/
  https://developer.wordpress.org/reference/functions/kses_init_filters/
  https://developer.wordpress.org/reference/functions/kses_remove_filters/
  https://developer.wordpress.org/reference/functions/wp_filter_post_kses/
- `map_meta_cap()`:
  https://developer.wordpress.org/reference/functions/map_meta_cap/
- `WP_REST_Comments_Controller::create_item_permissions_check()`:
  https://developer.wordpress.org/reference/classes/wp_rest_comments_controller/create_item_permissions_check/
- `_wp_post_revision_fields()` and its filter:
  https://developer.wordpress.org/reference/functions/_wp_post_revision_fields/
  https://developer.wordpress.org/reference/hooks/_wp_post_revision_fields/
- `wp_get_latest_revision_id_and_total_count()`:
  https://developer.wordpress.org/reference/functions/wp_get_latest_revision_id_and_total_count/
- `wp_encode_emoji()` and `wpdb::get_col_charset()`:
  https://developer.wordpress.org/reference/functions/wp_encode_emoji/
  https://developer.wordpress.org/reference/classes/wpdb/get_col_charset/
- REST routes and permission callbacks:
  https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
- Application Passwords:
  https://developer.wordpress.org/advanced-administration/security/application-passwords/
- MySQL locking functions:
  https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html
