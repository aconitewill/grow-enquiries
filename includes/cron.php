<?php
/**
 * Housekeeping. Daily: delete held spam after spam_days, and genuine
 * enquiries after retention_months (0 keeps them forever), in batches
 * until everything due is gone; then the digest, if one is due. Hourly:
 * retry emails that failed, up to GRE_MAX_TRIES times, then flag them.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/** Hourly retries before a failed email is flagged for a reply by hand. */
const GRE_MAX_TRIES = 3;

/**
 * Schedule the jobs if they aren't.
 */
function gre_schedule() {
	if ( ! wp_next_scheduled( 'gre_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gre_daily' );
	}
	if ( ! wp_next_scheduled( 'gre_hourly' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'gre_hourly' );
	}
}

/**
 * Delete everything matching, in batches of 200.
 *
 * @param string $before   strtotime-able "N days ago".
 * @param string $spam     '1' for held spam, '0' for genuine.
 * @return int Deleted.
 */
function gre_purge_batch( $before, $spam ) {
	$total = 0;
	for ( $round = 0; $round < 50; $round++ ) {
		$ids = get_posts(
			array(
				'post_type'      => GRE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'date_query'     => array( array( 'before' => $before ) ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_gre_spam',
						'value' => $spam,
					),
				),
			)
		);
		if ( ! $ids ) {
			break;
		}
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
			$total++;
		}
	}
	return $total;
}

/**
 * The daily job.
 */
function gre_daily() {
	$days = max( 1, (int) gre_setting( 'spam_days' ) );
	gre_purge_batch( $days . ' days ago', '1' );

	$months = (int) gre_setting( 'retention_months' );
	if ( $months > 0 ) {
		gre_purge_batch( $months . ' months ago', '0' );
	}
	update_option( 'grow_enquiries_last_purge', time(), false );

	gre_maybe_send_digest();
}
add_action( 'gre_daily', 'gre_daily' );

/**
 * Genuine enquiries whose email failed and that are still being retried.
 *
 * @return int[]
 */
function gre_retry_queue() {
	return get_posts(
		array(
			'post_type'      => GRE_POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_gre_spam',
					'value' => '0',
				),
				array(
					'key'   => '_gre_sent',
					'value' => '0',
				),
				array(
					'key'     => '_gre_flagged',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
}

/**
 * The hourly job: try each failed email again. One that has failed
 * GRE_MAX_TRIES retries is flagged, and from then on waits for a person
 * (Resend email, or a reply by hand). Anything tried in the last 50
 * minutes waits for the next run, so a fresh failure isn't retried a
 * minute later.
 *
 * @return array { sent, failed, flagged } counts.
 */
function gre_retry_failed() {
	$done = array(
		'sent'    => 0,
		'failed'  => 0,
		'flagged' => 0,
	);
	foreach ( gre_retry_queue() as $id ) {
		if ( time() - (int) gre_meta( $id, 'last_try' ) < 50 * MINUTE_IN_SECONDS ) {
			continue;
		}
		$tries = (int) gre_meta( $id, 'tries' ) + 1;
		update_post_meta( $id, '_gre_tries', $tries );
		if ( gre_deliver( $id ) ) {
			$done['sent']++;
			continue;
		}
		$done['failed']++;
		if ( $tries >= GRE_MAX_TRIES ) {
			update_post_meta( $id, '_gre_flagged', 1 );
			$done['flagged']++;

			/**
			 * An enquiry's email has failed every retry and needs a reply by hand.
			 *
			 * @param int $id Enquiry ID.
			 */
			do_action( 'grow_enquiry_flagged', $id );
		}
	}
	return $done;
}
add_action( 'gre_hourly', 'gre_retry_failed' );
