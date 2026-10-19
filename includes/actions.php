<?php
/**
 * Every action on an enquiry, shared by the view, row links, bulk actions
 * and WP-CLI. Loaded everywhere (not just wp-admin), so scripts and the
 * command line can release or mark enquiries too.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

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
 * Who may act: anyone with manage_enquiries, and WP-CLI, which already
 * has the run of the database.
 *
 * @return bool
 */
function gre_can_act() {
	return ( defined( 'WP_CLI' ) && WP_CLI ) || current_user_can( GRE_CAP );
}

/**
 * Do one action on one enquiry.
 *
 * @param string $do release | spam | block | read | resend | bin.
 * @param int    $id Enquiry.
 * @return bool|string True, false, or a result key for the notice.
 */
function gre_do_action( $do, $id ) {
	if ( GRE_POST_TYPE !== get_post_type( $id ) || ! gre_can_act() ) {
		return false;
	}
	switch ( $do ) {
		case 'release':
			if ( ! gre_meta( $id, 'spam' ) ) {
				return false;
			}
			update_post_meta( $id, '_gre_spam', 0 );
			update_post_meta( $id, '_gre_released', 1 );
			return gre_deliver( $id ) ? true : 'release_fail';

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
			if ( gre_meta( $id, 'spam' ) ) {
				return false;
			}
			return gre_deliver( $id ) ? true : 'resend_fail';

		case 'bin':
			return (bool) wp_trash_post( $id );
	}
	return false;
}
