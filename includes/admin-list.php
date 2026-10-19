<?php
/**
 * The Enquiries list: Inbox and Spam views, Read instead of Edit, unread
 * in bold with a count on the menu, and the bulk actions.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which view the list is showing: inbox (default) or spam.
 *
 * @return string
 */
function gre_current_view() {
	$v = isset( $_GET['gre_view'] ) ? sanitize_key( wp_unslash( $_GET['gre_view'] ) ) : 'inbox'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return 'spam' === $v ? 'spam' : 'inbox';
}

/**
 * Columns.
 *
 * @return array
 */
function gre_list_columns() {
	$cols = array(
		'cb'         => '<input type="checkbox" />',
		'title'      => __( 'From', 'grow-enquiries' ),
		'gre_about'  => __( 'About', 'grow-enquiries' ),
		'gre_status' => __( 'Status', 'grow-enquiries' ),
		'date'       => __( 'Received', 'grow-enquiries' ),
	);
	if ( 'spam' === gre_current_view() ) {
		$cols['gre_reason'] = __( 'Held because', 'grow-enquiries' );
	}
	return $cols;
}
add_filter( 'manage_' . GRE_POST_TYPE . '_posts_columns', 'gre_list_columns' );

/**
 * Column content.
 *
 * @param string $col Column.
 * @param int    $id  Enquiry.
 */
function gre_list_column( $col, $id ) {
	switch ( $col ) {
		case 'gre_about':
			echo esc_html( gre_meta( $id, 'about_label' ) ? gre_meta( $id, 'about_label' ) : __( 'General', 'grow-enquiries' ) );
			break;
		case 'gre_status':
			echo gre_status_badge( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			break;
		case 'gre_reason':
			echo esc_html( (string) gre_meta( $id, 'reason' ) );
			break;
	}
}
add_action( 'manage_' . GRE_POST_TYPE . '_posts_custom_column', 'gre_list_column', 10, 2 );

/**
 * A coloured status badge, in WordPress's own notice colours.
 *
 * @param int $id Enquiry.
 * @return string HTML.
 */
function gre_status_badge( $id ) {
	$status = gre_status( $id );
	$map    = array(
		'sent'   => array( '#00a32a', gre_meta( $id, 'released' ) ? __( 'Emailed (released)', 'grow-enquiries' ) : __( 'Emailed', 'grow-enquiries' ) ),
		'held'   => array( '#dba617', __( 'Held as spam', 'grow-enquiries' ) ),
		'failed' => array( '#d63638', __( 'Email failed', 'grow-enquiries' ) ),
	);
	list( $colour, $label ) = $map[ $status ];
	return '<span style="display:inline-block;padding:1px 8px;border-radius:10px;border:1px solid ' . $colour . ';color:' . $colour . ';font-size:12px;white-space:nowrap">' . esc_html( $label ) . '</span>';
}

/**
 * Print the email and first line under each row's name.
 *
 * @param WP_Post $post Post.
 */
function gre_list_row_summary( $post ) {
	if ( GRE_POST_TYPE !== $post->post_type ) {
		return;
	}
	$first = strtok( (string) gre_meta( $post->ID, 'message' ), "\n" );
	echo '<div class="gre-row-email">' . esc_html( (string) gre_meta( $post->ID, 'email' ) ) . '</div>';
	echo '<div class="gre-row-first">' . esc_html( wp_trim_words( (string) $first, 18 ) ) . '</div>';
}

/**
 * Row actions: Read, Release and send or Mark as spam, Bin.
 *
 * @param array   $actions Actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function gre_row_actions( $actions, $post ) {
	if ( GRE_POST_TYPE !== $post->post_type ) {
		return $actions;
	}
	$out = array();
	if ( 'trash' !== $post->post_status ) {
		$out['read'] = '<a href="' . esc_url( gre_view_url( $post->ID ) ) . '">' . esc_html__( 'Read', 'grow-enquiries' ) . '</a>';
		if ( gre_meta( $post->ID, 'spam' ) ) {
			$out['release'] = '<a href="' . esc_url( gre_action_url( 'release', $post->ID ) ) . '">' . esc_html__( 'Release and send', 'grow-enquiries' ) . '</a>';
		} else {
			$out['spam'] = '<a href="' . esc_url( gre_action_url( 'spam', $post->ID ) ) . '">' . esc_html__( 'Mark as spam', 'grow-enquiries' ) . '</a>';
		}
	}
	foreach ( array( 'trash', 'untrash', 'delete' ) as $keep ) {
		if ( isset( $actions[ $keep ] ) ) {
			$out[ $keep ] = $actions[ $keep ];
		}
	}
	gre_list_row_summary( $post );
	return $out;
}
add_filter( 'post_row_actions', 'gre_row_actions', 10, 2 );

/**
 * The title link and "edit" links go to the read-only view.
 *
 * @param string $link Link.
 * @param int    $id   Post.
 * @return string
 */
function gre_edit_link( $link, $id ) {
	return GRE_POST_TYPE === get_post_type( $id ) ? gre_view_url( $id ) : $link;
}
add_filter( 'get_edit_post_link', 'gre_edit_link', 10, 2 );

/**
 * Nobody edits an enquiry: the old editor redirects to the view.
 */
function gre_block_editor() {
	$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $id && GRE_POST_TYPE === get_post_type( $id ) ) {
		wp_safe_redirect( gre_view_url( $id ) );
		exit;
	}
}
add_action( 'load-post.php', 'gre_block_editor' );

/**
 * Views: Inbox and Spam (with counts) replace All and Private.
 *
 * @param array $views Views.
 * @return array
 */
function gre_views( $views ) {
	$base  = admin_url( 'edit.php?post_type=' . GRE_POST_TYPE );
	$trash = isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$cur   = $trash ? '' : gre_current_view();
	$out   = array();
	foreach ( array( 'inbox' => __( 'Inbox', 'grow-enquiries' ), 'spam' => __( 'Spam', 'grow-enquiries' ) ) as $key => $label ) {
		$out[ $key ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'gre_view', $key, $base ) ),
			$key === $cur ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			gre_count( 'spam' === $key )
		);
	}
	if ( isset( $views['trash'] ) ) {
		$out['trash'] = $views['trash'];
	}
	return $out;
}
add_filter( 'views_edit-' . GRE_POST_TYPE, 'gre_views' );

/**
 * Count of enquiries in a view.
 *
 * @param bool $spam Spam or inbox.
 * @return int
 */
function gre_count( $spam ) {
	$q = new WP_Query(
		array(
			'post_type'      => GRE_POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_gre_spam',
					'value' => $spam ? '1' : '0',
				),
			),
		)
	);
	return (int) $q->found_posts;
}

/**
 * Filter the list to the current view.
 *
 * @param WP_Query $q Query.
 */
function gre_filter_list( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || GRE_POST_TYPE !== $q->get( 'post_type' ) ) {
		return;
	}
	if ( 'trash' === $q->get( 'post_status' ) ) {
		return;
	}
	$q->set( 'post_status', 'private' );
	$q->set(
		'meta_query',
		array(
			array(
				'key'   => '_gre_spam',
				'value' => 'spam' === gre_current_view() ? '1' : '0',
			),
		)
	);
}
add_action( 'pre_get_posts', 'gre_filter_list' );

/**
 * Bulk actions: no Edit; Release and send, Mark as spam, Mark as read.
 *
 * @param array $actions Actions.
 * @return array
 */
function gre_bulk_actions( $actions ) {
	unset( $actions['edit'] );
	$out = array();
	if ( 'spam' === gre_current_view() ) {
		$out['gre_release'] = __( 'Release and send', 'grow-enquiries' );
	} else {
		$out['gre_spam'] = __( 'Mark as spam', 'grow-enquiries' );
		$out['gre_read'] = __( 'Mark as read', 'grow-enquiries' );
	}
	return array_merge( $out, $actions );
}
add_filter( 'bulk_actions-edit-' . GRE_POST_TYPE, 'gre_bulk_actions' );

/**
 * Do a bulk action.
 *
 * @param string $redirect URL.
 * @param string $action   Action.
 * @param int[]  $ids      Enquiries.
 * @return string
 */
function gre_handle_bulk( $redirect, $action, $ids ) {
	$map = array(
		'gre_release' => 'release',
		'gre_spam'    => 'spam',
		'gre_read'    => 'read',
	);
	if ( ! isset( $map[ $action ] ) || ! current_user_can( GRE_CAP ) ) {
		return $redirect;
	}
	$done = 0;
	foreach ( $ids as $id ) {
		if ( gre_do_action( $map[ $action ], (int) $id ) ) {
			$done++;
		}
	}
	return add_query_arg( array( 'gre_done' => $map[ $action ], 'gre_n' => $done ), $redirect );
}
add_filter( 'handle_bulk_actions-edit-' . GRE_POST_TYPE, 'gre_handle_bulk', 10, 3 );

/**
 * Unread rows in bold.
 *
 * @param string[] $classes Classes.
 * @param string[] $class   Extra.
 * @param int      $id      Post.
 * @return string[]
 */
function gre_row_class( $classes, $class, $id ) {
	if ( is_admin() && GRE_POST_TYPE === get_post_type( $id ) && ! gre_meta( $id, 'read' ) && ! gre_meta( $id, 'spam' ) ) {
		$classes[] = 'gre-unread';
	}
	return $classes;
}
add_filter( 'post_class', 'gre_row_class', 10, 3 );

/**
 * A little CSS for the list and the view.
 */
function gre_admin_css() {
	$screen = get_current_screen();
	if ( ! $screen || GRE_POST_TYPE !== $screen->post_type ) {
		return;
	}
	echo '<style>
		.gre-unread .row-title{font-weight:800}
		.gre-unread .row-title::before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:#2271b1;margin-right:6px;vertical-align:middle}
		.gre-row-email{color:#50575e}
		.gre-row-first{color:#787c82;margin-top:2px}
		.post-type-' . esc_attr( GRE_POST_TYPE ) . ' .page-title-action{display:none}
		.column-gre_status{width:150px}.column-gre_about{width:18%}
	</style>';
}
add_action( 'admin_head', 'gre_admin_css' );

/**
 * Unread count on the Enquiries menu, like Comments.
 */
function gre_menu_bubble() {
	global $menu;
	if ( ! current_user_can( GRE_CAP ) || ! is_array( $menu ) ) {
		return;
	}
	$n = gre_unread_count();
	if ( ! $n ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=' . GRE_POST_TYPE === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}
}
add_action( 'admin_menu', 'gre_menu_bubble', 99 );

/**
 * Notices after actions.
 */
function gre_admin_notices() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['gre_done'] ) ) {
		return;
	}
	$done = sanitize_key( wp_unslash( $_GET['gre_done'] ) );
	$n    = isset( $_GET['gre_n'] ) ? (int) $_GET['gre_n'] : 1;
	// phpcs:enable
	$text = array(
		'release'      => _n( 'Released and emailed on.', '%d enquiries released and emailed on.', $n, 'grow-enquiries' ),
		'release_fail' => __( 'Released, but the email failed. Reply from the enquiry instead.', 'grow-enquiries' ),
		'spam'         => _n( 'Marked as spam.', '%d enquiries marked as spam.', $n, 'grow-enquiries' ),
		'block'        => __( 'Marked as spam, and the sender is blocked from now on.', 'grow-enquiries' ),
		'read'         => _n( 'Marked as read.', '%d enquiries marked as read.', $n, 'grow-enquiries' ),
		'resend'       => __( 'Emailed again.', 'grow-enquiries' ),
		'resend_fail'  => __( 'The email failed again. Check the mail plugin under Enquiries, Settings, Tools.', 'grow-enquiries' ),
		'bin'          => __( 'Moved to the bin.', 'grow-enquiries' ),
	);
	if ( isset( $text[ $done ] ) ) {
		$bad = false !== strpos( $done, 'fail' );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $bad ? 'error' : 'success', esc_html( sprintf( $text[ $done ], $n ) ) );
	}
}
add_action( 'admin_notices', 'gre_admin_notices' );
