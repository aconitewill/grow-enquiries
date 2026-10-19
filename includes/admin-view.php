<?php
/**
 * The read-only enquiry view, and every action on an enquiry.
 *
 * An enquiry is a record, so it's read, never edited: no autosave, no
 * revisions, no "someone else is editing", no comment boxes.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the view as a hidden page under Enquiries.
 */
function gre_register_view_page() {
	add_submenu_page(
		'edit.php?post_type=' . GRE_POST_TYPE,
		__( 'Enquiry', 'grow-enquiries' ),
		__( 'Enquiry', 'grow-enquiries' ),
		GRE_CAP,
		'grow-enquiry',
		'gre_render_view'
	);
}
add_action( 'admin_menu', 'gre_register_view_page' );

/**
 * Keep it out of the menu itself.
 */
function gre_hide_view_page() {
	remove_submenu_page( 'edit.php?post_type=' . GRE_POST_TYPE, 'grow-enquiry' );
}
add_action( 'admin_head', 'gre_hide_view_page' );

/**
 * A nonced URL for an action.
 *
 * @param string $do Action.
 * @param int    $id Enquiry.
 * @return string
 */
function gre_action_url( $do, $id ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=gre_act&do=' . $do . '&id=' . (int) $id ), 'gre_' . $do . '_' . (int) $id );
}

/**
 * Do one action on one enquiry. Shared by the view, row links and bulk.
 *
 * @param string $do release | spam | block | read | resend | bin.
 * @param int    $id Enquiry.
 * @return bool|string True, false, or a result key for the notice.
 */
function gre_do_action( $do, $id ) {
	if ( GRE_POST_TYPE !== get_post_type( $id ) || ! current_user_can( GRE_CAP ) ) {
		return false;
	}
	switch ( $do ) {
		case 'release':
			if ( ! gre_meta( $id, 'spam' ) ) {
				return false;
			}
			$sent = gre_send( $id );
			update_post_meta( $id, '_gre_spam', 0 );
			update_post_meta( $id, '_gre_released', 1 );
			update_post_meta( $id, '_gre_sent', $sent ? 1 : 0 );
			return $sent ? true : 'release_fail';

		case 'block':
			$email = strtolower( (string) gre_meta( $id, 'email' ) );
			if ( is_email( $email ) ) {
				$saved            = (array) get_option( GRE_OPTION, array() );
				$list             = isset( $saved['blocked'] ) ? (string) $saved['blocked'] : '';
				$saved['blocked'] = trim( $list . "\n" . $email );
				update_option( GRE_OPTION, $saved );
				gre_settings_flush();
			}
			// Then mark as spam.
		case 'spam':
			update_post_meta( $id, '_gre_spam', 1 );
			if ( ! gre_meta( $id, 'reason' ) ) {
				update_post_meta( $id, '_gre_reason', __( 'marked as spam by hand', 'grow-enquiries' ) );
			}
			return true;

		case 'read':
			update_post_meta( $id, '_gre_read', 1 );
			return true;

		case 'resend':
			$sent = gre_send( $id );
			update_post_meta( $id, '_gre_sent', $sent ? 1 : 0 );
			return $sent ? true : 'resend_fail';

		case 'bin':
			return (bool) wp_trash_post( $id );
	}
	return false;
}

/**
 * The action endpoint for links and buttons.
 */
function gre_handle_action() {
	$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	check_admin_referer( 'gre_' . $do . '_' . $id );
	if ( ! current_user_can( GRE_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'grow-enquiries' ) );
	}

	$result = gre_do_action( $do, $id );
	$key    = is_string( $result ) ? $result : $do;

	$back = 'bin' === $do
		? admin_url( 'edit.php?post_type=' . GRE_POST_TYPE . '&gre_view=' . ( gre_meta( $id, 'spam' ) ? 'spam' : 'inbox' ) )
		: gre_view_url( $id );
	wp_safe_redirect( add_query_arg( 'gre_done', $key, $back ) );
	exit;
}
add_action( 'admin_post_gre_act', 'gre_handle_action' );

/**
 * Previous and next in the same view, by date.
 *
 * @param int  $id   Enquiry.
 * @param bool $newer Newer (previous in the list) or older.
 * @return int
 */
function gre_neighbour( $id, $newer ) {
	$ids = get_posts(
		array(
			'post_type'      => GRE_POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => $newer ? 'ASC' : 'DESC',
			'date_query'     => array( array( $newer ? 'after' : 'before' => get_post_field( 'post_date', $id ) ) ),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_gre_spam',
					'value' => gre_meta( $id, 'spam' ) ? '1' : '0',
				),
			),
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * The view.
 */
function gre_render_view() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $id || GRE_POST_TYPE !== get_post_type( $id ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Enquiry not found', 'grow-enquiries' ) . '</h1></div>';
		return;
	}

	// Opening it marks it read.
	update_post_meta( $id, '_gre_read', 1 );

	$name    = (string) gre_meta( $id, 'name' );
	$email   = (string) gre_meta( $id, 'email' );
	$message = (string) gre_meta( $id, 'message' );
	$status  = gre_status( $id );
	$list    = admin_url( 'edit.php?post_type=' . GRE_POST_TYPE . '&gre_view=' . ( 'held' === $status ? 'spam' : 'inbox' ) );
	$prev    = gre_neighbour( $id, true );
	$next    = gre_neighbour( $id, false );
	$about   = (int) gre_meta( $id, 'about' );

	$quoted = '';
	foreach ( preg_split( '/\R/', $message ) as $line ) {
		$quoted .= '> ' . $line . "\n";
	}
	$reply = 'mailto:' . rawurlencode( $email )
		. '?subject=' . rawurlencode( 'Re: ' . gre_subject( $id ) )
		. '&body=' . rawurlencode( "\n\n" . $name . ' wrote on ' . get_the_date( 'j F Y', $id ) . ":\n" . $quoted );
	?>
	<div class="wrap gre-view">
		<p class="gre-view__nav">
			<a href="<?php echo esc_url( $list ); ?>">&larr; <?php echo 'held' === $status ? esc_html__( 'Spam', 'grow-enquiries' ) : esc_html__( 'Inbox', 'grow-enquiries' ); ?></a>
			<span class="gre-view__pn">
				<?php if ( $prev ) : ?>
					<a href="<?php echo esc_url( gre_view_url( $prev ) ); ?>">&lsaquo; <?php esc_html_e( 'Newer', 'grow-enquiries' ); ?></a>
				<?php endif; ?>
				<?php if ( $next ) : ?>
					<a href="<?php echo esc_url( gre_view_url( $next ) ); ?>"><?php esc_html_e( 'Older', 'grow-enquiries' ); ?> &rsaquo;</a>
				<?php endif; ?>
			</span>
		</p>

		<h1><?php echo esc_html( $name ); ?></h1>
		<p><?php echo gre_status_badge( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="gre-view__when"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Received %s', 'grow-enquiries' ), get_the_date( 'jS F, Y \a\t H:i', $id ) ) ); ?></span>
		</p>

		<table class="gre-view__details">
			<?php foreach ( gre_details( $id ) as $label => $value ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<?php if ( 'Email' === $label && is_email( $value ) ) : ?>
							<a href="mailto:<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></a>
							<button type="button" class="button-link gre-copy" data-copy="<?php echo esc_attr( $value ); ?>"><?php esc_html_e( 'Copy', 'grow-enquiries' ); ?></button>
						<?php elseif ( 'About' === $label && $about && get_permalink( $about ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $about ) ); ?>"><?php echo esc_html( $value ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $value ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2 class="gre-view__h"><?php esc_html_e( 'Message', 'grow-enquiries' ); ?></h2>
		<div class="gre-view__message"><?php echo esc_html( $message ); ?></div>

		<?php if ( 'held' === $status && gre_meta( $id, 'reason' ) ) : ?>
			<p class="gre-view__reason"><strong><?php esc_html_e( 'Held as spam because:', 'grow-enquiries' ); ?></strong> <?php echo esc_html( (string) gre_meta( $id, 'reason' ) ); ?></p>
		<?php endif; ?>

		<p class="gre-view__actions">
			<?php if ( is_email( $email ) ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $reply ); ?>"><?php esc_html_e( 'Reply', 'grow-enquiries' ); ?></a>
			<?php endif; ?>
			<?php if ( 'held' === $status ) : ?>
				<a class="button" href="<?php echo esc_url( gre_action_url( 'release', $id ) ); ?>"><?php esc_html_e( 'Release and send', 'grow-enquiries' ); ?></a>
			<?php else : ?>
				<?php if ( 'failed' === $status ) : ?>
					<a class="button" href="<?php echo esc_url( gre_action_url( 'resend', $id ) ); ?>"><?php esc_html_e( 'Resend email', 'grow-enquiries' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( gre_action_url( 'spam', $id ) ); ?>"><?php esc_html_e( 'Mark as spam', 'grow-enquiries' ); ?></a>
				<a class="button" href="<?php echo esc_url( gre_action_url( 'block', $id ) ); ?>"><?php esc_html_e( 'Block sender', 'grow-enquiries' ); ?></a>
			<?php endif; ?>
			<a class="button button-link-delete" href="<?php echo esc_url( gre_action_url( 'bin', $id ) ); ?>"><?php esc_html_e( 'Move to Bin', 'grow-enquiries' ); ?></a>
		</p>
	</div>
	<style>
		.gre-view{max-width:820px}
		.gre-view__nav{display:flex;justify-content:space-between;margin:14px 0 0}
		.gre-view__pn a{margin-left:14px}
		.gre-view__when{margin-left:10px;color:#646970}
		.gre-view__details{border-collapse:collapse;margin:16px 0 0}
		.gre-view__details th{text-align:left;padding:6px 24px 6px 0;vertical-align:top;color:#50575e;font-weight:600;white-space:nowrap}
		.gre-view__details td{padding:6px 0}
		.gre-copy{margin-left:8px}
		.gre-view__h{margin:26px 0 8px;font-size:14px}
		.gre-view__message{white-space:pre-wrap;overflow-wrap:anywhere;background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;padding:16px 18px;font-size:14px;line-height:1.6}
		.gre-view__reason{margin:14px 0 0;padding:10px 14px;background:#fcf9e8;border-left:4px solid #dba617}
		.gre-view__actions{margin:22px 0 0;display:flex;gap:8px;flex-wrap:wrap}
	</style>
	<script>
		document.querySelectorAll('.gre-copy').forEach(function(b){b.addEventListener('click',function(){
			if(navigator.clipboard){navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function(){b.textContent='Copied';});}
		});});
	</script>
	<?php
}
