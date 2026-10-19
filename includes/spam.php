<?php
/**
 * Spam checks. All cache-safe: nothing depends on a server-rendered token.
 *
 * Every check returns a reason; a submission with any reason is held
 * (logged, not emailed) and the sender still sees the thank-you.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/** The value form.js writes into the hidden token field. */
const GRE_JS_TOKEN = 'gre-human';

/**
 * The base list: label => array( pattern, weight ). Labels are what a
 * site lists under "Ignored terms" to switch a rule off. Merged from the
 * Adrian Paye and Seven Dunes modules (19th October 2026), minus the
 * holiday-let terms, which are a sector pack.
 *
 * @return array
 */
function gre_base_terms() {
	return array(
		// Weight 3: enough on their own.
		'seo'                       => array( '\bseo\b|s\.e\.o', 3 ),
		'backlinks'                 => array( 'back ?links?', 3 ),
		'link building'             => array( 'link[- ]?building', 3 ),
		'guest posts'               => array( 'guest ?posts?', 3 ),
		'domain authority'          => array( 'domain authority', 3 ),
		'search engine optimisation' => array( 'search engine optimi[sz]', 3 ),
		'first page of google'      => array( '(first|1st|top) page (of|on) google|page (one|1) (of|on) google|google.{0,15}(ranking|first page|1st page)', 3 ),
		'rank higher'               => array( '\brank(ing|ings)? (higher|better|on google)', 3 ),
		'website traffic'           => array( '(website|site|organic|more) traffic', 3 ),
		'lead generation'           => array( 'lead generation', 3 ),
		'white label'               => array( 'white[- ]label', 3 ),
		'crypto and casino'         => array( '\bcrypto|bitcoin|casino', 3 ),
		'ai visibility'             => array( '\bai (seo|search|visibility|optimi[sz]ation|overviews?|rankings?)', 3 ),
		'generative engine optimisation' => array( '(generative|answer) engine optimi[sz]|\baeo\b', 3 ),
		'chatgpt visibility'        => array( '(chatgpt|perplexity|gemini|llms?).{0,40}(visib|recommend|rank|cite|mention)', 3 ),
		'rankings'                  => array( '\brankings?\b', 3 ),
		// Weight 2: need a second signal.
		'dear website owner'        => array( 'dear (website|site) owner', 2 ),
		'dear sir or madam'         => array( 'dear (sir|madam)', 2 ),
		'came across your website'  => array( 'i (came across|was (checking|looking at|browsing|reviewing)) your (website|site)', 2 ),
		'redesign'                  => array( 'redesign', 2 ),
		'web design'                => array( 'web ?design', 2 ),
		'digital marketing'         => array( 'digital marketing', 2 ),
		'marketing agency'          => array( 'marketing (agency|services|team)', 2 ),
		'social media management'   => array( 'social media (management|marketing)', 2 ),
		'app development'           => array( 'app development', 2 ),
		'outsourcing'               => array( 'outsourc', 2 ),
		'keywords'                  => array( '\bkeywords?\b', 2 ),
		'ppc'                       => array( '\bppc\b', 2 ),
		'geo'                       => array( '\bgeo\b', 2 ),
		'chatgpt'                   => array( '\b(chatgpt|llms?)\b', 2 ),
	);
}

/**
 * Sector packs, ticked per site.
 *
 * @return array slug => array( label, terms )
 */
function gre_sector_packs() {
	return array(
		'holiday_lets' => array(
			'label' => 'Holiday lets',
			'terms' => array(
				'list your property'   => array( 'list(ing)? your (property|apartment|holiday let)', 2 ),
				'channel manager'      => array( 'channel manager', 2 ),
				'property management'  => array( 'property management (company|services)', 2 ),
				'more bookings'        => array( '(increase|boost|double) (your )?(bookings|occupancy|revenue)', 2 ),
			),
		),
	);
}

/**
 * Plain words into a safe pattern: "rank higher | 2" becomes a word-
 * bounded, quoted pattern with weight 2. Nobody writes regex.
 *
 * @param string $text One term per line.
 * @return array label => array( pattern, weight )
 */
function gre_parse_terms( $text ) {
	$out = array();
	foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$weight = 3;
		if ( false !== strpos( $line, '|' ) ) {
			list( $line, $w ) = array_map( 'trim', explode( '|', $line, 2 ) );
			$weight           = max( 1, min( 10, (int) $w ) );
		}
		$phrase = strtolower( $line );
		if ( '' === $phrase ) {
			continue;
		}
		$out[ $phrase ] = array( '\b' . str_replace( ' ', '\s+', preg_quote( $phrase, '/' ) ) . '\b', $weight );
	}
	return $out;
}

/**
 * The terms in force on this site, after packs, extras and ignores.
 *
 * @return array label => array( pattern, weight )
 */
function gre_active_terms() {
	$terms = gre_base_terms();

	$packs = gre_sector_packs();
	foreach ( (array) gre_setting( 'packs' ) as $pack ) {
		if ( isset( $packs[ $pack ] ) ) {
			$terms = array_merge( $terms, $packs[ $pack ]['terms'] );
		}
	}

	$terms = array_merge( $terms, gre_parse_terms( gre_setting( 'extra_terms' ) ) );

	foreach ( preg_split( '/\R/', strtolower( (string) gre_setting( 'ignored_terms' ) ) ) as $ignore ) {
		$ignore = trim( $ignore );
		if ( '' !== $ignore ) {
			unset( $terms[ $ignore ] );
		}
	}

	/**
	 * Code-level control over the terms.
	 *
	 * @param array $terms label => array( pattern, weight ).
	 */
	return (array) apply_filters( 'grow_enquiry_spam_terms', $terms );
}

/**
 * Score a message.
 *
 * @param string $name    Sender's name.
 * @param string $message Message (and any extra field text).
 * @return array { score, hits }
 */
function gre_spam_score( $name, $message ) {
	$text  = strtolower( $name . "\n" . $message );
	$score = 0;
	$hits  = array();

	foreach ( gre_active_terms() as $label => $term ) {
		list( $pattern, $weight ) = $term;
		if ( @preg_match( '/' . $pattern . '/u', $text, $m ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a bad filtered pattern must not break the form.
			$score += (int) $weight;
			$hits[] = $label;
		}
	}

	if ( gre_setting( 'links_suspicious' ) ) {
		$links = preg_match_all( '#(https?://|www\.)#i', $text );
		if ( $links ) {
			$score += min( 4, 2 * $links );
			$hits[] = $links . ' link' . ( $links > 1 ? 's' : '' );
		}
	}
	if ( preg_match( '#(https?://|www\.|\.com\b)#i', $name ) ) {
		$score += 3;
		$hits[] = 'link in name';
	}

	return array(
		'score' => $score,
		'hits'  => array_values( array_unique( $hits ) ),
	);
}

/**
 * Is this sender on the blocked list? Whole addresses or @domains.
 *
 * @param string $email Address.
 * @return bool
 */
function gre_is_blocked( $email ) {
	$email = strtolower( trim( $email ) );
	foreach ( preg_split( '/\R/', strtolower( (string) gre_setting( 'blocked' ) ) ) as $rule ) {
		$rule = trim( $rule );
		if ( '' === $rule ) {
			continue;
		}
		if ( $rule === $email || ( '@' === $rule[0] && substr( $email, -strlen( $rule ) ) === $rule ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The visitor's IP. CF-Connecting-IP only when the site is set as being
 * behind Cloudflare; anywhere else that header is spoofable.
 *
 * @return string
 */
function gre_ip() {
	if ( gre_setting( 'behind_cloudflare' ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
	}
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/**
 * Per-IP rate limit. The IP is hashed for the counter and never stored
 * with the enquiry.
 *
 * @return bool False once the hour's allowance is used.
 */
function gre_rate_ok() {
	$key = 'gre_rate_' . md5( wp_salt( 'nonce' ) . gre_ip() );
	$hit = (int) get_transient( $key );
	if ( $hit >= (int) gre_setting( 'rate_limit' ) ) {
		return false;
	}
	set_transient( $key, $hit + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * Cloudflare Turnstile. True when it's off, and true when Cloudflare
 * can't be reached, so real visitors aren't punished for an outage.
 *
 * @param string $token Widget token.
 * @return bool
 */
function gre_turnstile_ok( $token ) {
	if ( ! gre_turnstile_on() ) {
		return true;
	}
	if ( '' === $token ) {
		return false;
	}
	$res = wp_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => gre_setting( 'turnstile_secret' ),
				'response' => $token,
				'remoteip' => gre_ip(),
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return true;
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	return ! empty( $json['success'] );
}

/**
 * Every check, in one place. Empty array means clean.
 *
 * @param array $sub The cleaned submission (name, email, message, extras text).
 * @param array $raw The raw POST (for the hidden fields).
 * @return string[] Reasons, for the log. Never shown to the sender.
 */
function gre_spam_reasons( $sub, $raw ) {
	$reasons = array();

	if ( ! empty( $raw['gre_website'] ) ) {
		$reasons[] = 'hidden field filled';
	}

	$js = isset( $raw['gre_js'] ) ? (string) $raw['gre_js'] : '';
	if ( GRE_JS_TOKEN !== $js ) {
		$reasons[] = 'no JavaScript';
	} else {
		$elapsed = isset( $raw['gre_elapsed'] ) ? (int) $raw['gre_elapsed'] : 0;
		if ( $elapsed < (int) gre_setting( 'min_seconds' ) ) {
			$reasons[] = 'sent in ' . $elapsed . 's';
		}
	}

	$ts = isset( $raw['cf-turnstile-response'] ) ? sanitize_text_field( (string) $raw['cf-turnstile-response'] ) : '';
	if ( ! gre_turnstile_ok( $ts ) ) {
		$reasons[] = 'failed Turnstile';
	}

	if ( gre_is_blocked( $sub['email'] ) ) {
		$reasons[] = 'blocked sender';
	}

	$content = gre_spam_score( $sub['name'], $sub['message'] . "\n" . $sub['extras_text'] );
	if ( $content['score'] >= (int) gre_setting( 'threshold' ) ) {
		$reasons[] = 'content: ' . implode( ', ', $content['hits'] );
	}

	return $reasons;
}
