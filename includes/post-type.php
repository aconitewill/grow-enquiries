<?php
/**
 * The Enquiries log: a private post type. Nothing public, no REST, no
 * "Add new". Everything about an enquiry lives in post meta with the
 * _gre_ prefix; post_content keeps a plain-text copy so search works.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the post type. Every capability maps to manage_enquiries.
 */
function gre_register_post_type() {
	register_post_type(
		GRE_POST_TYPE,
		array(
			'labels'              => array(
				'name'          => __( 'Enquiries', 'grow-enquiries' ),
				'singular_name' => __( 'Enquiry', 'grow-enquiries' ),
				'menu_name'     => __( 'Enquiries', 'grow-enquiries' ),
				'all_items'     => __( 'Enquiries', 'grow-enquiries' ),
				'search_items'  => __( 'Search enquiries', 'grow-enquiries' ),
				'not_found'     => __( 'No enquiries here.', 'grow-enquiries' ),
				'not_found_in_trash' => __( 'Nothing in the bin.', 'grow-enquiries' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-email-alt',
			'menu_position'       => 26,
			'supports'            => array( 'title' ),
			'rewrite'             => false,
			'query_var'           => false,
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'edit_post'              => GRE_CAP,
				'read_post'              => GRE_CAP,
				'delete_post'            => GRE_CAP,
				'edit_posts'             => GRE_CAP,
				'edit_others_posts'      => GRE_CAP,
				'edit_private_posts'     => GRE_CAP,
				'edit_published_posts'   => GRE_CAP,
				'read_private_posts'     => GRE_CAP,
				'delete_posts'           => GRE_CAP,
				'delete_others_posts'    => GRE_CAP,
				'delete_private_posts'   => GRE_CAP,
				'delete_published_posts' => GRE_CAP,
				'publish_posts'          => 'do_not_allow',
				'create_posts'           => 'do_not_allow',
			),
		)
	);
}
add_action( 'init', 'gre_register_post_type' );

/**
 * Link to the read-only view of an enquiry.
 *
 * @param int $id Enquiry.
 * @return string
 */
function gre_view_url( $id ) {
	return admin_url( 'edit.php?post_type=' . GRE_POST_TYPE . '&page=grow-enquiry&id=' . (int) $id );
}

/**
 * Meta for one enquiry.
 *
 * @param int    $id  Enquiry.
 * @param string $key Key without the _gre_ prefix.
 * @return mixed
 */
function gre_meta( $id, $key ) {
	return get_post_meta( $id, '_gre_' . $key, true );
}

/**
 * Save a submission. Returns the enquiry ID, or 0.
 *
 * @param array    $sub     Cleaned submission.
 * @param string[] $reasons Spam reasons; empty for a clean one.
 * @return int
 */
function gre_log( $sub, $reasons ) {
	$copy  = $sub['name'] . ' <' . $sub['email'] . ">\n";
	$copy .= $sub['about_label'] ? 'About: ' . $sub['about_label'] . "\n" : '';
	$copy .= "\n" . $sub['message'] . "\n";
	if ( $sub['extras_text'] ) {
		$copy .= "\n" . $sub['extras_text'] . "\n";
	}

	$id = wp_insert_post(
		array(
			'post_type'      => GRE_POST_TYPE,
			'post_status'    => 'private',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_title'     => $sub['name'],
			'post_content'   => $copy,
			'post_author'    => 0,
		),
		true
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}

	$meta = array(
		'name'        => $sub['name'],
		'email'       => $sub['email'],
		'phone'       => $sub['phone'],
		'message'     => $sub['message'],
		'about'       => (int) $sub['about'],
		'about_label' => $sub['about_label'],
		'extras'      => $sub['extras'],
		'form_id'     => $sub['form_id'],
		'page'        => $sub['page'],
		'spam'        => $reasons ? 1 : 0,
		'reason'      => implode( '; ', $reasons ),
		'sent'        => 0,
		'read'        => 0,
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, '_gre_' . $key, $value );
	}

	return (int) $id;
}

/**
 * Status of an enquiry, for badges and logic.
 *
 * @param int $id Enquiry.
 * @return string held | sent | failed
 */
function gre_status( $id ) {
	if ( gre_meta( $id, 'spam' ) ) {
		return 'held';
	}
	return gre_meta( $id, 'sent' ) ? 'sent' : 'failed';
}

/**
 * Unread, not-spam enquiries, for the menu bubble.
 *
 * @return int
 */
function gre_unread_count() {
	$q = new WP_Query(
		array(
			'post_type'      => GRE_POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_gre_spam',
					'value' => '0',
				),
				array(
					'key'   => '_gre_read',
					'value' => '0',
				),
			),
		)
	);
	return (int) $q->found_posts;
}

/**
 * The meta query for a view of the log.
 *
 * @param string $view inbox | spam | failed (genuine, email not sent).
 * @return array
 */
function gre_view_meta_query( $view ) {
	$q = array(
		array(
			'key'   => '_gre_spam',
			'value' => 'spam' === $view ? '1' : '0',
		),
	);
	if ( 'failed' === $view ) {
		$q[] = array(
			'key'   => '_gre_sent',
			'value' => '0',
		);
	}
	return $q;
}

/**
 * How many enquiries are in a view, optionally since a time.
 *
 * @param string $view  inbox | spam | failed.
 * @param int    $since Received after this timestamp (0: any time).
 * @return int
 */
function gre_view_count( $view, $since = 0 ) {
	$args = array(
		'post_type'      => GRE_POST_TYPE,
		'post_status'    => 'private',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => gre_view_meta_query( $view ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	);
	if ( $since ) {
		$args['date_query'] = array(
			array(
				'column' => 'post_date_gmt',
				'after'  => gmdate( 'Y-m-d H:i:s', $since ),
			),
		);
	}
	return (int) ( new WP_Query( $args ) )->found_posts;
}

/**
 * Genuine enquiries whose email hasn't gone.
 *
 * @return int
 */
function gre_failed_count() {
	return gre_view_count( 'failed' );
}

/**
 * Those that have failed every retry and wait for a person.
 *
 * @return int
 */
function gre_flagged_count() {
	$q = new WP_Query(
		array(
			'post_type'      => GRE_POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				gre_view_meta_query( 'failed' ),
				array(
					array(
						'key'   => '_gre_flagged',
						'value' => '1',
					),
				)
			),
		)
	);
	return (int) $q->found_posts;
}
