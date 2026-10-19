<?php
/**
 * The digest: an email listing what the spam filter held since the last
 * one, plus any genuine enquiry whose email failed, each linking to its
 * enquiry view where it can be released or answered. Weekly (Mondays) or
 * monthly (the 1st), checked by the daily job. Only sent when there's
 * something in it.
 *
 * With a 60-day spam purge and a monthly digest, nothing is deleted
 * before it has been in a digest.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * Start of the current digest period, in the site's timezone: Monday
 * this week or the 1st of this month. 0 when the digest is off.
 *
 * @param int|null $now Timestamp, for testing.
 * @return int
 */
function gre_digest_period_start( $now = null ) {
	$freq = (string) gre_setting( 'digest' );
	if ( ! in_array( $freq, array( 'weekly', 'monthly' ), true ) ) {
		return 0;
	}
	$today = ( new DateTimeImmutable( '@' . ( null === $now ? time() : (int) $now ) ) )->setTimezone( wp_timezone() );
	$start = 'weekly' === $freq ? $today->modify( 'monday this week' ) : $today->modify( 'first day of this month' );
	return $start->setTime( 0, 0 )->getTimestamp();
}

/**
 * When the last digest period was closed off. 0 if never.
 *
 * @return int
 */
function gre_digest_last() {
	return (int) get_option( 'grow_enquiries_last_digest', 0 );
}

/**
 * What would go in a digest now.
 *
 * @param int $since Held spam received after this timestamp (0: all).
 * @return array { held: int[], failed: int[] }
 */
function gre_digest_items( $since ) {
	$held_args = array(
		'post_type'      => GRE_POST_TYPE,
		'post_status'    => 'private',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_gre_spam',
				'value' => '1',
			),
		),
	);
	if ( $since > 0 ) {
		$held_args['date_query'] = array(
			array(
				'column' => 'post_date_gmt',
				'after'  => gmdate( 'Y-m-d H:i:s', $since ),
			),
		);
	}

	$failed = get_posts(
		array(
			'post_type'      => GRE_POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_gre_spam',
					'value' => '0',
				),
				array(
					'key'   => '_gre_sent',
					'value' => '0',
				),
			),
		)
	);

	return array(
		'held'   => array_map( 'intval', get_posts( $held_args ) ),
		'failed' => array_map( 'intval', $failed ),
	);
}

/**
 * The daily check: if a new period has started since the last digest,
 * send one covering everything held since then, and close the period off
 * either way.
 *
 * @return string off | not_due | empty | sent | failed
 */
function gre_maybe_send_digest() {
	$start = gre_digest_period_start();
	if ( ! $start ) {
		return 'off';
	}
	$last = gre_digest_last();
	if ( $last >= $start ) {
		return 'not_due';
	}
	$result = gre_send_digest( $last );
	if ( 'failed' !== $result ) {
		// A failed send is tried again by tomorrow's run.
		update_option( 'grow_enquiries_last_digest', time(), false );
	}
	return $result;
}

/**
 * Send a digest of everything since $since.
 *
 * @param int  $since   Timestamp; 0 for everything held.
 * @param bool $preview A preview from the Tools tab: says so in the subject.
 * @return string empty | sent | failed
 */
function gre_send_digest( $since, $preview = false ) {
	$items = gre_digest_items( $since );
	if ( ! $items['held'] && ! $items['failed'] ) {
		return 'empty';
	}

	$parts = array();
	if ( $items['failed'] ) {
		/* translators: %d: number of enquiries */
		$parts[] = sprintf( _n( '%d not emailed', '%d not emailed', count( $items['failed'] ), 'grow-enquiries' ), count( $items['failed'] ) );
	}
	if ( $items['held'] ) {
		/* translators: %d: number of enquiries */
		$parts[] = sprintf( _n( '%d held as spam', '%d held as spam', count( $items['held'] ), 'grow-enquiries' ), count( $items['held'] ) );
	}
	$subject = ( $preview ? __( 'Preview: ', 'grow-enquiries' ) : '' ) . '[' . gre_domain() . '] ' . __( 'Enquiries digest', 'grow-enquiries' ) . ': ' . implode( ', ', $parts );

	$headers = array();
	if ( gre_setting( 'from_email' ) && is_email( gre_setting( 'from_email' ) ) ) {
		$from      = gre_setting( 'from_name' ) ? gre_setting( 'from_name' ) : get_bloginfo( 'name' );
		$headers[] = sprintf( 'From: %s <%s>', str_replace( array( '<', '>', "\r", "\n", '"' ), '', $from ), gre_setting( 'from_email' ) );
	}
	if ( 'plain' === gre_setting( 'format' ) ) {
		$body = gre_digest_plain( $items, $since );
	} else {
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$body      = gre_digest_html( $items, $since );
	}

	$ok = (bool) wp_mail( gre_digest_recipients(), $subject, $body, $headers );
	if ( $ok && ! $preview ) {
		update_option( 'grow_enquiries_last_digest_sent', time(), false );
	}
	return $ok ? 'sent' : 'failed';
}

/**
 * One line of a message for the digest: the first line, trimmed, with
 * links taken out so nothing in a spam message is clickable.
 *
 * @param int $id Enquiry.
 * @return string Plain text.
 */
function gre_digest_snippet( $id ) {
	$first = trim( (string) strtok( trim( (string) gre_meta( $id, 'message' ) ), "\n" ) );
	$first = preg_replace( '#\b(?:https?://|www\.)\S+#i', '[link]', $first );
	return wp_trim_words( $first, 22 );
}

/**
 * Up to this many of each list go in the email; the rest are counted.
 */
const GRE_DIGEST_MAX = 100;

/**
 * The HTML digest.
 *
 * @param array $items From gre_digest_items().
 * @param int   $since Start of the period.
 * @return string
 */
function gre_digest_html( $items, $since ) {
	$accent = sanitize_hex_color( (string) gre_setting( 'accent' ) );
	$accent = $accent ? $accent : '#2271b1';
	$font   = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif';
	$muted  = 'color:#777;font-size:13px;';

	$list = function ( $ids, $held ) use ( $accent, $muted ) {
		$html = '';
		foreach ( array_slice( $ids, 0, GRE_DIGEST_MAX ) as $id ) {
			$html .= '<div style="padding:12px 0;border-top:1px solid #e5e5e5;">';
			$html .= '<div><strong>' . esc_html( (string) gre_meta( $id, 'name' ) ) . '</strong> <span style="' . $muted . '">' . esc_html( (string) gre_meta( $id, 'email' ) ) . ' &middot; ' . esc_html( get_the_date( 'j M Y, H:i', $id ) ) . '</span></div>';
			$html .= '<div style="margin:3px 0;">' . esc_html( gre_digest_snippet( $id ) ) . '</div>';
			if ( $held ) {
				$html .= '<div style="' . $muted . '">' . esc_html__( 'Held because:', 'grow-enquiries' ) . ' ' . esc_html( (string) gre_meta( $id, 'reason' ) ) . '</div>';
			} elseif ( gre_meta( $id, 'mail_error' ) ) {
				$html .= '<div style="' . $muted . '">' . esc_html__( 'Email error:', 'grow-enquiries' ) . ' ' . esc_html( (string) gre_meta( $id, 'mail_error' ) ) . '</div>';
			}
			$html .= '<div style="margin-top:4px;font-size:13px;"><a href="' . esc_url( gre_view_url( $id ) ) . '" style="color:' . $accent . ';">' . ( $held ? esc_html__( 'Open to release it', 'grow-enquiries' ) : esc_html__( 'Open to reply', 'grow-enquiries' ) ) . '</a></div>';
			$html .= '</div>';
		}
		if ( count( $ids ) > GRE_DIGEST_MAX ) {
			/* translators: %d: number */
			$html .= '<p style="' . $muted . '">' . esc_html( sprintf( __( 'And %d more.', 'grow-enquiries' ), count( $ids ) - GRE_DIGEST_MAX ) ) . '</p>';
		}
		return $html;
	};

	$out  = '<div style="font-family:' . $font . ';color:#222;font-size:15px;line-height:1.5;max-width:620px;">';
	$out .= '<h2 style="margin:0 0 4px;font-size:19px;color:' . $accent . ';">' . esc_html__( 'Enquiries digest', 'grow-enquiries' ) . '</h2>';
	$out .= '<p style="margin:0 0 20px;' . $muted . '">' . esc_html( gre_digest_period_text( $since ) ) . '</p>';

	if ( $items['failed'] ) {
		$out .= '<h3 style="margin:24px 0 4px;font-size:16px;color:#d63638;">' . esc_html__( 'Not emailed', 'grow-enquiries' ) . ' (' . count( $items['failed'] ) . ')</h3>';
		$out .= '<p style="margin:0 0 6px;' . $muted . '">' . esc_html__( 'Genuine enquiries whose email failed. They are safe in the log; reply to them from there.', 'grow-enquiries' ) . '</p>';
		$out .= $list( $items['failed'], false );
	}
	if ( $items['held'] ) {
		$out .= '<h3 style="margin:24px 0 4px;font-size:16px;">' . esc_html__( 'Held as spam', 'grow-enquiries' ) . ' (' . count( $items['held'] ) . ')</h3>';
		$out .= '<p style="margin:0 0 6px;' . $muted . '">' . esc_html__( 'Not emailed when they arrived. Skim for anything genuine and release it; the rest delete themselves.', 'grow-enquiries' ) . '</p>';
		$out .= $list( $items['held'], true );
	}

	$out .= '<p style="margin:28px 0 0;' . $muted . '">' . esc_html( gre_digest_footer() ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . GRE_POST_TYPE . '&page=grow-enquiries-settings&tab=retention' ) ) . '" style="color:#777;">' . esc_html__( 'Digest settings', 'grow-enquiries' ) . '</a>.</p>';
	$out .= '</div>';
	return $out;
}

/**
 * The plain-text digest.
 *
 * @param array $items From gre_digest_items().
 * @param int   $since Start of the period.
 * @return string
 */
function gre_digest_plain( $items, $since ) {
	$list = function ( $ids, $held ) {
		$text = '';
		foreach ( array_slice( $ids, 0, GRE_DIGEST_MAX ) as $id ) {
			$text .= "\n" . gre_meta( $id, 'name' ) . ' <' . gre_meta( $id, 'email' ) . '>, ' . get_the_date( 'j M Y, H:i', $id ) . "\n";
			$text .= gre_digest_snippet( $id ) . "\n";
			if ( $held ) {
				$text .= __( 'Held because:', 'grow-enquiries' ) . ' ' . gre_meta( $id, 'reason' ) . "\n";
			} elseif ( gre_meta( $id, 'mail_error' ) ) {
				$text .= __( 'Email error:', 'grow-enquiries' ) . ' ' . gre_meta( $id, 'mail_error' ) . "\n";
			}
			$text .= gre_view_url( $id ) . "\n";
		}
		if ( count( $ids ) > GRE_DIGEST_MAX ) {
			/* translators: %d: number */
			$text .= "\n" . sprintf( __( 'And %d more.', 'grow-enquiries' ), count( $ids ) - GRE_DIGEST_MAX ) . "\n";
		}
		return $text;
	};

	$out = __( 'Enquiries digest', 'grow-enquiries' ) . "\n" . gre_digest_period_text( $since ) . "\n";
	if ( $items['failed'] ) {
		$out .= "\n" . strtoupper( __( 'Not emailed', 'grow-enquiries' ) ) . ' (' . count( $items['failed'] ) . ")\n";
		$out .= __( 'Genuine enquiries whose email failed. They are safe in the log; reply to them from there.', 'grow-enquiries' ) . "\n";
		$out .= $list( $items['failed'], false );
	}
	if ( $items['held'] ) {
		$out .= "\n" . strtoupper( __( 'Held as spam', 'grow-enquiries' ) ) . ' (' . count( $items['held'] ) . ")\n";
		$out .= __( 'Not emailed when they arrived. Skim for anything genuine and release it; the rest delete themselves.', 'grow-enquiries' ) . "\n";
		$out .= $list( $items['held'], true );
	}
	$out .= "\n--\n" . gre_digest_footer() . "\n";
	return $out;
}

/**
 * "Held since 1st October, 2026 on example.com."
 *
 * @param int $since Timestamp.
 * @return string
 */
function gre_digest_period_text( $since ) {
	return $since
		/* translators: 1: date, 2: domain */
		? sprintf( __( 'Since %1$s, from the form on %2$s.', 'grow-enquiries' ), wp_date( 'jS F, Y', $since ), gre_domain() )
		/* translators: %s: domain */
		: sprintf( __( 'From the form on %s.', 'grow-enquiries' ), gre_domain() );
}

/**
 * The footer line: when spam deletes itself, and how often this comes.
 *
 * @return string
 */
function gre_digest_footer() {
	return sprintf(
		/* translators: 1: days, 2: weekly or monthly */
		__( 'Held spam deletes itself after %1$d days. This digest comes %2$s, only when there is something in it.', 'grow-enquiries' ),
		max( 1, (int) gre_setting( 'spam_days' ) ),
		'weekly' === gre_setting( 'digest' ) ? __( 'every Monday', 'grow-enquiries' ) : __( 'on the 1st of each month', 'grow-enquiries' )
	);
}
