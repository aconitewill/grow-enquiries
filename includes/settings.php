<?php
/**
 * Settings: one option array, with defaults. The settings screen is in
 * admin-settings.php.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defaults. The recommended answers from the plan are baked in here.
 *
 * @return array
 */
function gre_defaults() {
	return array(
		// Delivery.
		'recipients'        => '',          // One per line. Blank: the site admin email.
		'from_name'         => '',          // Blank: left to the mail plugin.
		'from_email'        => '',
		'subject'           => '[{domain}] {topic} from {name}',
		'format'            => 'html',      // html | plain.
		'accent'            => '#2271b1',

		// Spam protection.
		'turnstile_site'    => '',
		'turnstile_secret'  => '',
		'packs'             => array(),     // Sector packs, e.g. holiday_lets.
		'extra_terms'       => '',          // "phrase" or "phrase | weight", one per line.
		'ignored_terms'     => '',          // Labels of base terms that are genuine here.
		'links_suspicious'  => 1,
		'blocked'           => '',          // Emails or @domains, one per line.
		'threshold'         => 3,
		'min_seconds'       => 5,
		'rate_limit'        => 5,           // Per IP per hour.
		'behind_cloudflare' => 0,           // Trust CF-Connecting-IP.

		// Retention and digest.
		'spam_days'         => 60,
		'retention_months'  => 24,          // 0 = keep forever.
		'digest'            => 'monthly',   // off | weekly | monthly.
		'digest_to'         => '',          // Blank: the recipients.

		// Form.
		'thanks_heading'    => 'Thank you, your message is on its way.',
		'thanks_text'       => "You'll hear back soon.",
		'privacy_note'      => "We'll only use your details to reply to you.",
		'button'            => 'Send message',
		'phone'             => 0,
		'stylesheet'        => 1,
	);
}

/**
 * All settings, merged over the defaults. Turnstile keys can come from
 * wp-config.php constants instead (GRE_TURNSTILE_SITE_KEY and
 * GRE_TURNSTILE_SECRET_KEY), which win over the database.
 *
 * @return array
 */
function gre_settings() {
	if ( isset( $GLOBALS['gre_settings_cache'] ) ) {
		return $GLOBALS['gre_settings_cache'];
	}
	$saved = get_option( GRE_OPTION, array() );
	$s     = array_merge( gre_defaults(), is_array( $saved ) ? $saved : array() );

	if ( defined( 'GRE_TURNSTILE_SITE_KEY' ) && defined( 'GRE_TURNSTILE_SECRET_KEY' ) ) {
		$s['turnstile_site']   = (string) GRE_TURNSTILE_SITE_KEY;
		$s['turnstile_secret'] = (string) GRE_TURNSTILE_SECRET_KEY;
	}

	$GLOBALS['gre_settings_cache'] = (array) apply_filters( 'grow_enquiry_settings', $s );
	return $GLOBALS['gre_settings_cache'];
}

/**
 * Forget the cached settings (after a save).
 */
function gre_settings_flush() {
	unset( $GLOBALS['gre_settings_cache'] );
}

/**
 * One setting.
 *
 * @param string $key Key.
 * @return mixed
 */
function gre_setting( $key ) {
	$s = gre_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : null;
}

/**
 * Where enquiries go. Never printed on the front end.
 *
 * @return string[]
 */
function gre_recipients() {
	$list = gre_parse_addresses( (string) gre_setting( 'recipients' ) );
	return $list ? $list : array( get_option( 'admin_email' ) );
}

/**
 * Valid addresses from a list separated by lines, commas or spaces.
 *
 * @param string $text Addresses.
 * @return string[]
 */
function gre_parse_addresses( $text ) {
	$list = array();
	foreach ( preg_split( '/[\s,;]+/', $text ) as $addr ) {
		$addr = sanitize_email( $addr );
		if ( is_email( $addr ) ) {
			$list[] = $addr;
		}
	}
	return array_values( array_unique( $list ) );
}

/**
 * Where the digest goes: its own address if set, otherwise the
 * recipients.
 *
 * @return string[]
 */
function gre_digest_recipients() {
	$list = gre_parse_addresses( (string) gre_setting( 'digest_to' ) );
	return $list ? $list : gre_recipients();
}

/**
 * Is Turnstile switched on?
 *
 * @return bool
 */
function gre_turnstile_on() {
	return '' !== trim( (string) gre_setting( 'turnstile_site' ) ) && '' !== trim( (string) gre_setting( 'turnstile_secret' ) );
}
