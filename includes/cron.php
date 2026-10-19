<?php
/**
 * Daily housekeeping: delete held spam after spam_days, and genuine
 * enquiries after retention_months (0 keeps them forever). Keeps going
 * until everything due is gone, in batches.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schedule the daily job if it isn't.
 */
function gre_schedule() {
	if ( ! wp_next_scheduled( 'gre_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gre_daily' );
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
}
add_action( 'gre_daily', 'gre_daily' );
