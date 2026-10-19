<?php
/**
 * WP-CLI: wp grow-enquiries <command>.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read and act on enquiries from the command line.
 */
class GRE_CLI {

	/**
	 * List enquiries.
	 *
	 * ## OPTIONS
	 *
	 * [--view=<view>]
	 * : inbox, spam or failed (genuine, email not sent).
	 * ---
	 * default: inbox
	 * ---
	 *
	 * [--format=<format>]
	 * : table, csv, json, ids.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Named.
	 */
	public function list_( $args, $assoc ) {
		$view = in_array( $assoc['view'], array( 'inbox', 'spam', 'failed' ), true ) ? $assoc['view'] : 'inbox';
		$ids  = get_posts(
			array(
				'post_type'      => GRE_POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => gre_view_meta_query( $view ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		if ( 'ids' === $assoc['format'] ) {
			WP_CLI::line( implode( ' ', $ids ) );
			return;
		}
		$rows = array();
		foreach ( $ids as $id ) {
			$rows[] = array(
				'ID'       => $id,
				'received' => get_the_date( 'Y-m-d H:i', $id ),
				'status'   => gre_status( $id ) . ( gre_meta( $id, 'flagged' ) ? ' (flagged)' : '' ),
				'name'     => gre_meta( $id, 'name' ),
				'email'    => gre_meta( $id, 'email' ),
				'read'     => gre_meta( $id, 'read' ) ? 'yes' : 'no',
				'reason'   => gre_meta( $id, 'reason' ),
			);
		}
		WP_CLI\Utils\format_items( $assoc['format'], $rows, array( 'ID', 'received', 'status', 'name', 'email', 'read', 'reason' ) );
	}

	/**
	 * Release held enquiries and email them on.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Enquiry IDs.
	 *
	 * @param array $args IDs.
	 */
	public function release( $args ) {
		$this->act( 'release', $args );
	}

	/**
	 * Mark enquiries as spam.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Enquiry IDs.
	 *
	 * @param array $args IDs.
	 */
	public function spam( $args ) {
		$this->act( 'spam', $args );
	}

	/**
	 * Mark enquiries as read.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Enquiry IDs.
	 *
	 * @param array $args IDs.
	 */
	public function read( $args ) {
		$this->act( 'read', $args );
	}

	/**
	 * Email enquiries again.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Enquiry IDs.
	 *
	 * @param array $args IDs.
	 */
	public function resend( $args ) {
		$this->act( 'resend', $args );
	}

	/**
	 * Move enquiries to the bin.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Enquiry IDs.
	 *
	 * @param array $args IDs.
	 */
	public function bin( $args ) {
		$this->act( 'bin', $args );
	}

	/**
	 * Run the hourly retry of failed emails now.
	 */
	public function retry() {
		$done = gre_retry_failed();
		WP_CLI::success( sprintf( 'Sent %d, failed %d, flagged %d.', $done['sent'], $done['failed'], $done['flagged'] ) );
	}

	/**
	 * Send the digest.
	 *
	 * ## OPTIONS
	 *
	 * [--preview]
	 * : Send what the next digest would hold, now, without moving the period on.
	 *
	 * [--if-due]
	 * : Only send if a new period has started, as the daily job does.
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Named.
	 */
	public function digest( $args, $assoc ) {
		if ( ! empty( $assoc['if-due'] ) ) {
			$result = gre_maybe_send_digest();
		} else {
			$result = gre_send_digest( gre_digest_last(), ! empty( $assoc['preview'] ) );
		}
		if ( 'failed' === $result ) {
			WP_CLI::error( 'The digest email failed.' );
		}
		WP_CLI::success( 'Digest: ' . $result . '.' );
	}

	/**
	 * One action on several enquiries.
	 *
	 * @param string $do  Action.
	 * @param array  $ids IDs.
	 */
	private function act( $do, $ids ) {
		$bad = 0;
		foreach ( $ids as $id ) {
			$result = gre_do_action( $do, (int) $id );
			if ( true === $result ) {
				WP_CLI::log( "{$id}: done." );
			} else {
				$bad++;
				WP_CLI::warning( "{$id}: " . ( is_string( $result ) ? $result : 'not an enquiry, or not possible.' ) );
			}
		}
		if ( $bad ) {
			WP_CLI::error( "{$bad} of " . count( $ids ) . ' failed.' );
		}
		WP_CLI::success( count( $ids ) . ' done.' );
	}
}

WP_CLI::add_command( 'grow-enquiries', 'GRE_CLI' );
