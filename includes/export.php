<?php
/**
 * CSV export of the view on screen (Inbox, Spam, Not emailed or Bin),
 * with the same search, month and From / To dates as the list. Spam is
 * only ever exported from the Spam view.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * From and To boxes and the Export button, above the list. They sit in
 * the list's own filter form, so Filter applies the dates to the list
 * and Export CSV exports exactly what Filter would show.
 *
 * @param string $post_type Post type.
 * @param string $which     top | bottom.
 */
function gre_export_controls( $post_type, $which = 'top' ) {
	if ( GRE_POST_TYPE !== $post_type || 'top' !== $which || ! current_user_can( GRE_CAP ) ) {
		return;
	}
	$r     = gre_date_range();
	$trash = isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $trash ) {
		echo '<input type="hidden" name="gre_view" value="' . esc_attr( gre_current_view() ) . '">';
	}
	printf(
		'<span class="gre-range"><label for="gre-from">%s</label> <input type="date" id="gre-from" name="gre_from" value="%s"> <label for="gre-to">%s</label> <input type="date" id="gre-to" name="gre_to" value="%s"></span>',
		esc_html__( 'From', 'grow-enquiries' ),
		esc_attr( $r['from'] ),
		esc_html__( 'to', 'grow-enquiries' ),
		esc_attr( $r['to'] )
	);
	wp_nonce_field( 'gre_export', 'gre_export_nonce', false );
	echo '<button type="submit" name="gre_export" value="1" class="button">' . esc_html__( 'Export CSV', 'grow-enquiries' ) . '</button> ';
}
add_action( 'restrict_manage_posts', 'gre_export_controls', 10, 2 );

/**
 * Stream the CSV, before the list screen renders anything.
 */
function gre_maybe_export() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['gre_export'] ) || ! isset( $_GET['post_type'] ) || GRE_POST_TYPE !== $_GET['post_type'] ) {
		return;
	}
	check_admin_referer( 'gre_export', 'gre_export_nonce' );
	if ( ! current_user_can( GRE_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'grow-enquiries' ) );
	}
	$trash  = isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'];
	$view   = $trash ? 'bin' : gre_current_view();
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$month  = isset( $_GET['m'] ) ? absint( $_GET['m'] ) : 0;
	// phpcs:enable

	$args = array(
		'post_type'      => GRE_POST_TYPE,
		'post_status'    => $trash ? 'trash' : 'private',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);
	if ( ! $trash ) {
		$args['meta_query'] = gre_view_meta_query( $view ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}
	if ( gre_date_query() ) {
		$args['date_query'] = gre_date_query();
	}
	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	if ( $month ) {
		$args['m'] = $month;
	}
	$ids = get_posts( $args );

	$range = gre_date_range();
	$name  = 'enquiries-' . $view . ( $range['from'] ? '-from-' . $range['from'] : '' ) . ( $range['to'] ? '-to-' . $range['to'] : '' ) . '-' . wp_date( 'Y-m-d' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );

	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM, so Excel reads UTF-8.
	gre_csv_row( $out, gre_csv_header() );
	foreach ( $ids as $id ) {
		gre_csv_row( $out, gre_csv_fields( (int) $id ) );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'load-edit.php', 'gre_maybe_export' );

/**
 * @return string[]
 */
function gre_csv_header() {
	return array( 'Received', 'Status', 'Name', 'Email', 'Phone', 'About', 'Message', 'Other fields', 'Sent from', 'Held because', 'Email error', 'Link' );
}

/**
 * One enquiry as a CSV row.
 *
 * @param int $id Enquiry.
 * @return string[]
 */
function gre_csv_fields( $id ) {
	$status = array(
		'sent'   => gre_meta( $id, 'released' ) ? 'Emailed (released)' : 'Emailed',
		'held'   => 'Held as spam',
		'failed' => gre_meta( $id, 'flagged' ) ? 'Not emailed' : 'Email failed, retrying',
	);
	$extras = array();
	foreach ( (array) gre_meta( $id, 'extras' ) as $label => $value ) {
		if ( '' !== (string) $value ) {
			$extras[] = $label . ': ' . $value;
		}
	}
	return array(
		get_the_date( 'Y-m-d H:i', $id ),
		$status[ gre_status( $id ) ],
		(string) gre_meta( $id, 'name' ),
		(string) gre_meta( $id, 'email' ),
		(string) gre_meta( $id, 'phone' ),
		(string) gre_meta( $id, 'about_label' ),
		(string) gre_meta( $id, 'message' ),
		implode( "\n", $extras ),
		(string) gre_meta( $id, 'page' ),
		gre_meta( $id, 'spam' ) ? (string) gre_meta( $id, 'reason' ) : '',
		(string) gre_meta( $id, 'mail_error' ),
		gre_view_url( $id ),
	);
}

/**
 * Write a row. Anything a spreadsheet would run as a formula gets a
 * leading apostrophe: messages come from strangers.
 *
 * @param resource $out    Stream.
 * @param string[] $fields Cells.
 */
function gre_csv_row( $out, $fields ) {
	foreach ( $fields as $i => $f ) {
		$f = (string) $f;
		if ( '' !== $f && in_array( $f[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$f = "'" . $f;
		}
		$fields[ $i ] = $f;
	}
	fputcsv( $out, $fields, ',', '"', '' );
}
