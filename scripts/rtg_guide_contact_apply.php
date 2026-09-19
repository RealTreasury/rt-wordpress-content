<?php
/**
 * Two production changes for the Tech Selection Guide funnel.
 *
 *   1. New RT Gate form "Tech Selection Guide Download" = the General Form's
 *      fields plus a "Would you like us to reach out?" checkbox, notifying
 *      contact@ + Tim + Tracey. Mapping 8 (the guidebook asset) is repointed
 *      at it so no other gated asset changes.
 *   2. Post 4585 (/treasury-tech-selection-guide/thank-you/) gains a
 *      "Schedule a Call" block pointing at Tracey's Calendly.
 *
 * Idempotent. Plan-only unless RTG_APPLY=1. Snapshots every row it touches
 * into the plugin's own revision tables and prints the old post_content.
 */
global $wpdb;
$apply = ( getenv( 'RTG_APPLY' ) === '1' );
$forms = $wpdb->prefix . 'rtg_forms';
$maps  = $wpdb->prefix . 'rtg_mappings';

$NAME       = 'Tech Selection Guide Download';
$MAPPING_ID = 8;
$POST_ID    = 4585;

/* ---------- 1. the form ---------- */
$src = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$forms} WHERE id = %d", 2 ), ARRAY_A );
if ( ! $src ) { echo "FATAL: form 2 missing\n"; return; }
$fields = json_decode( $src['fields_schema'], true );
if ( ! is_array( $fields ) ) { echo "FATAL: form 2 fields_schema unparseable\n"; return; }

$has = false;
foreach ( $fields as $f ) { if ( isset( $f['key'] ) && 'contact_request' === $f['key'] ) { $has = true; } }
if ( ! $has ) {
	$fields[] = array(
		'key'          => 'contact_request',
		'label'        => 'Would you like us to reach out?',
		'type'         => 'checkbox',
		'required'     => false,
		'autocomplete' => false,
		'options'      => array( 'Yes, contact me about my selection' ),
	);
}
$email_settings = wp_json_encode( array(
	'lead_email_mode'     => 'none',
	'internal_notify'     => true,
	'internal_recipients' => 'contact@realtreasury.com, Tschultz@realtreasury.com, tknight@realtreasury.com',
) );

$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$forms} WHERE name = %s", $NAME ), ARRAY_A );
$mapping  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$maps} WHERE id = %d", $MAPPING_ID ), ARRAY_A );

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

$page_done = ( false !== strpos( $content, 'rt-gbty-call' ) );
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
echo "form: " . ( $existing ? "UPDATE id {$existing['id']}" : "INSERT \"{$NAME}\"" ) . "\n";
echo "mapping {$MAPPING_ID}: form_id " . ( $mapping ? $mapping['form_id'] : '?' ) . " -> (new form)\n";
echo "post {$POST_ID}: " . ( $page_done ? 'already has the call block, no change' : 'insert call block (' . strlen( $content ) . ' -> ' . strlen( $new_content ) . " bytes)" ) . "\n";
if ( ! $apply ) { echo "\nPLAN ONLY. Re-run with RTG_APPLY=1 to write.\n"; return; }

/* ---------- apply ---------- */
if ( $existing ) {
	$form_id = (int) $existing['id'];
	$wpdb->insert( $wpdb->prefix . 'rtg_form_revisions', array( 'form_id' => $form_id, 'snapshot' => wp_json_encode( $existing ), 'edited_by' => 0, 'restored_from_revision_id' => 0 ), array( '%d', '%s', '%d', '%d' ) );
	$wpdb->update( $forms, array( 'fields_schema' => wp_json_encode( $fields ), 'consent_text' => $src['consent_text'], 'email_settings' => $email_settings ), array( 'id' => $form_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
	echo "UPDATED form {$form_id}\n";
} else {
	$wpdb->insert( $forms, array( 'name' => $NAME, 'fields_schema' => wp_json_encode( $fields ), 'consent_text' => $src['consent_text'], 'email_settings' => $email_settings ), array( '%s', '%s', '%s', '%s' ) );
	$form_id = (int) $wpdb->insert_id;
	echo "INSERTED form {$form_id}\n";
}
if ( ! $form_id ) { echo "FATAL: no form id (" . $wpdb->last_error . ") — nothing else written\n"; return; }

if ( $mapping && (int) $mapping['form_id'] !== $form_id ) {
	$wpdb->insert( $wpdb->prefix . 'rtg_mapping_revisions', array( 'mapping_id' => $MAPPING_ID, 'snapshot' => wp_json_encode( $mapping ), 'edited_by' => 0, 'restored_from_revision_id' => 0 ), array( '%d', '%s', '%d', '%d' ) );
	$wpdb->update( $maps, array( 'form_id' => $form_id ), array( 'id' => $MAPPING_ID ), array( '%d' ), array( '%d' ) );
	echo "MAPPING {$MAPPING_ID} -> form {$form_id}\n";
} else {
	echo "MAPPING {$MAPPING_ID} unchanged\n";
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
echo wp_json_encode( $wpdb->get_row( $wpdb->prepare( "SELECT id, name, email_settings FROM {$forms} WHERE id = %d", $form_id ), ARRAY_A ) ) . "\n";
echo wp_json_encode( $wpdb->get_row( $wpdb->prepare( "SELECT id, form_id, asset_id, resend_segment_id FROM {$maps} WHERE id = %d", $MAPPING_ID ), ARRAY_A ) ) . "\n";
