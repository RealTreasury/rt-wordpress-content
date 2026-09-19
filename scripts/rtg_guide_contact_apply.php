<?php
/**
 * Two production changes for the gated-content funnel.
 *
 *   1. RT Gate form 2 ("General Form", the one every gated asset points at)
 *      gains one optional, unchecked-by-default checkbox asking whether the
 *      visitor wants us to reach out. The answer rides along on the internal
 *      notification that form 2 already sends.
 *   2. Post 4585 (/treasury-tech-selection-guide/thank-you/) gains a
 *      "Schedule a Call" block pointing at Tracey's Calendly, which is where
 *      scheduling now lives after coming out of the Resend emails.
 *
 * Idempotent. Plan-only unless RTG_APPLY=1. Snapshots form 2 into the plugin's
 * own revision table and writes the old post_content to uploads/ before it
 * touches either one, then reads back what it wrote.
 */
global $wpdb;
$apply = ( getenv( 'RTG_APPLY' ) === '1' );
$forms = $wpdb->prefix . 'rtg_forms';

$FORM_ID = 2;
$POST_ID = 4585;

/* ---------- 1. the checkbox ---------- */
$form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$forms} WHERE id = %d", $FORM_ID ), ARRAY_A );
if ( ! $form ) { echo "FATAL: form {$FORM_ID} missing\n"; return; }

$fields = json_decode( $form['fields_schema'], true );
if ( ! is_array( $fields ) ) { echo "FATAL: form {$FORM_ID} fields_schema unparseable\n"; return; }

$form_done = false;
foreach ( $fields as $f ) { if ( isset( $f['key'] ) && 'contact_request' === $f['key'] ) { $form_done = true; } }
if ( ! $form_done ) {
	/* required=false is what leaves it unchecked: the renderer only writes a
	   `required` attribute, never `checked`, so an untouched box submits ''. */
	$fields[] = array(
		'key'          => 'contact_request',
		'label'        => 'Would you like us to reach out?',
		'type'         => 'checkbox',
		'required'     => false,
		'autocomplete' => false,
		'options'      => array( 'Yes, please contact me' ),
	);
}

/* ---------- 2. the page ---------- */
$post = get_post( $POST_ID );
if ( ! $post ) { echo "FATAL: post {$POST_ID} missing\n"; return; }
$content = $post->post_content;

$css_anchor = "    .rt-gbty-back:hover { filter: brightness(1.05); }\n";
$css_add    = $css_anchor . "\n"
	. "    /* Talk-to-us block. Same specificity trap as `.rt-gbty-back` above: the\n"
	. "       label needs the element qualifier or `.rt-gbty a` wins. */\n"
	. "    .rt-gbty-talk { margin: 36px 0 0; padding: 28px 0 0; border-top: 1px solid rgba(114,22,244,0.14); }\n"
	. "    .rt-gbty-talk-heading { font-size: 1.15rem; font-weight: 700; margin: 0 0 8px; color: var(--dark-text); }\n"
	. "    .rt-gbty a.rt-gbty-call { display: inline-block; margin-top: 16px; padding: 13px 26px; font-weight: 700; color: var(--primary-purple); background: #fff; border: 2px solid var(--primary-purple); border-radius: 12px; text-decoration: none; }\n"
	. "    .rt-gbty-call:hover { background: rgba(114,22,244,0.06); }\n";

$back_anchor = '    <a class="rt-gbty-back" href="https://realtreasury.com">Back to Real Treasury</a>';
$back_add    = "    <div class=\"rt-gbty-talk\">\n"
	. "      <p class=\"rt-gbty-talk-heading\">Want to talk it through?</p>\n"
	. "      <p class=\"rt-gbty-text\">Book 30 minutes with Tracey Knight. No pitch &mdash; we take no vendor fees for recommendations.</p>\n"
	. "      <a class=\"rt-gbty-call\" href=\"https://calendly.com/tknight-realtreasury/30min\" target=\"_blank\" rel=\"noopener\">Schedule a Call</a>\n"
	. "    </div>\n\n"
	. $back_anchor;

$page_done   = ( false !== strpos( $content, 'rt-gbty-call' ) );
$new_content = $content;
if ( ! $page_done ) {
	if ( 1 !== substr_count( $content, $css_anchor ) || 1 !== substr_count( $content, $back_anchor ) ) {
		echo "FATAL: post {$POST_ID} anchors not unique — content drifted. css="
			. substr_count( $content, $css_anchor ) . " back=" . substr_count( $content, $back_anchor ) . "\n";
		return;
	}
	$new_content = str_replace( $css_anchor, $css_add, $content );
	$new_content = str_replace( $back_anchor, $back_add, $new_content );
}

echo "=== PLAN ===\n";
echo "form {$FORM_ID}: " . ( $form_done ? 'already has contact_request, no change' : 'append contact_request checkbox (' . count( $fields ) . " fields after)" ) . "\n";
echo "form {$FORM_ID} email_settings: UNCHANGED -> " . $form['email_settings'] . "\n";
echo "post {$POST_ID}: " . ( $page_done ? 'already has the call block, no change' : 'insert call block (' . strlen( $content ) . ' -> ' . strlen( $new_content ) . ' bytes)' ) . "\n";
if ( ! $apply ) { echo "\nPLAN ONLY. Re-run with RTG_APPLY=1 to write.\n"; return; }

/* ---------- apply ---------- */
if ( ! $form_done ) {
	$wpdb->insert( $wpdb->prefix . 'rtg_form_revisions', array(
		'form_id' => $FORM_ID, 'snapshot' => wp_json_encode( $form ),
		'edited_by' => 0, 'restored_from_revision_id' => 0,
	), array( '%d', '%s', '%d', '%d' ) );
	$ok = $wpdb->update( $forms, array( 'fields_schema' => wp_json_encode( $fields ) ), array( 'id' => $FORM_ID ), array( '%s' ), array( '%d' ) );
	echo ( false === $ok ) ? "ERROR: form update failed: " . $wpdb->last_error . "\n" : "FORM {$FORM_ID} updated\n";
}

if ( ! $page_done ) {
	$backup = WP_CONTENT_DIR . "/uploads/rt-post-{$POST_ID}-" . gmdate( 'Ymd-His' ) . '.bak.html';
	file_put_contents( $backup, $content );
	echo "BACKUP {$backup}\n";
	$wpdb->update( $wpdb->posts, array( 'post_content' => $new_content ), array( 'ID' => $POST_ID ), array( '%s' ), array( '%d' ) );
	clean_post_cache( $POST_ID );
	$check = get_post( $POST_ID )->post_content;
	echo ( $check === $new_content ) ? "POST {$POST_ID} written and verified\n" : "ERROR: post {$POST_ID} read-back MISMATCH\n";
}

echo "=== READ BACK ===\n";
$after = $wpdb->get_row( $wpdb->prepare( "SELECT fields_schema, email_settings FROM {$forms} WHERE id = %d", $FORM_ID ), ARRAY_A );
$keys  = array();
foreach ( (array) json_decode( $after['fields_schema'], true ) as $f ) { $keys[] = $f['key']; }
echo "form {$FORM_ID} fields: " . implode( ', ', $keys ) . "\n";
echo "form {$FORM_ID} email_settings: " . $after['email_settings'] . "\n";
echo "post {$POST_ID} has call block: " . ( false !== strpos( get_post( $POST_ID )->post_content, 'rt-gbty-call' ) ? 'yes' : 'no' ) . "\n";
