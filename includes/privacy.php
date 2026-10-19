<?php
/**
 * Privacy: WordPress's own Tools, Export Personal Data and Erase Personal
 * Data find enquiries by email address, and the privacy policy guide
 * (Settings, Privacy) gets suggested wording that matches this site's
 * settings.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/** Enquiries handled per page of an export or erase request. */
const GRE_PRIVACY_PAGE = 50;

/**
 * Every enquiry from one address, in any status, including the bin.
 *
 * @param string $email Address.
 * @param int    $page  1-based page.
 * @return int[]
 */
function gre_enquiries_by_email( $email, $page = 1 ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return array();
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => GRE_POST_TYPE,
				'post_status'    => array( 'private', 'publish', 'draft', 'pending', 'trash' ),
				'posts_per_page' => GRE_PRIVACY_PAGE,
				'paged'          => max( 1, (int) $page ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_gre_email',
						'value' => $email,
					),
				),
			)
		)
	);
}

/**
 * Register the exporter and eraser.
 *
 * @param array $list Exporters or erasers.
 * @return array
 */
function gre_register_exporter( $list ) {
	$list['grow-enquiries'] = array(
		'exporter_friendly_name' => __( 'Enquiries', 'grow-enquiries' ),
		'callback'               => 'gre_privacy_export',
	);
	return $list;
}
add_filter( 'wp_privacy_personal_data_exporters', 'gre_register_exporter' );

/**
 * @param array $list Erasers.
 * @return array
 */
function gre_register_eraser( $list ) {
	$list['grow-enquiries'] = array(
		'eraser_friendly_name' => __( 'Enquiries', 'grow-enquiries' ),
		'callback'             => 'gre_privacy_erase',
	);
	return $list;
}
add_filter( 'wp_privacy_personal_data_erasers', 'gre_register_eraser' );

/**
 * Export: one item per enquiry, with everything the visitor sent.
 *
 * @param string $email Address.
 * @param int    $page  Page.
 * @return array
 */
function gre_privacy_export( $email, $page = 1 ) {
	$ids  = gre_enquiries_by_email( $email, $page );
	$data = array();
	foreach ( $ids as $id ) {
		$rows = array(
			array(
				'name'  => __( 'Received', 'grow-enquiries' ),
				'value' => get_the_date( 'jS F, Y, H:i', $id ),
			),
		);
		foreach ( gre_details( $id ) as $label => $value ) {
			$rows[] = array(
				'name'  => $label,
				'value' => $value,
			);
		}
		$rows[] = array(
			'name'  => __( 'Message', 'grow-enquiries' ),
			'value' => (string) gre_meta( $id, 'message' ),
		);
		$data[] = array(
			'group_id'          => 'grow-enquiries',
			'group_label'       => __( 'Enquiries', 'grow-enquiries' ),
			'group_description' => __( 'Messages sent through the enquiry form on this site.', 'grow-enquiries' ),
			'item_id'           => 'enquiry-' . $id,
			'data'              => $rows,
		);
	}
	return array(
		'data' => $data,
		'done' => count( $ids ) < GRE_PRIVACY_PAGE,
	);
}

/**
 * Erase: delete every enquiry from that address, for good. Always asks
 * for page 1, because the previous page's enquiries are gone.
 *
 * @param string $email Address.
 * @param int    $page  Page (ignored, see above).
 * @return array
 */
function gre_privacy_erase( $email, $page = 1 ) {
	$ids     = gre_enquiries_by_email( $email, 1 );
	$removed = false;
	$kept    = false;
	foreach ( $ids as $id ) {
		if ( wp_delete_post( $id, true ) ) {
			$removed = true;
		} else {
			$kept = true;
		}
	}
	return array(
		'items_removed'  => $removed,
		'items_retained' => $kept,
		'messages'       => $kept ? array( __( 'Some enquiries could not be deleted.', 'grow-enquiries' ) ) : array(),
		'done'           => count( $ids ) < GRE_PRIVACY_PAGE || $kept,
	);
}

/**
 * The suggested privacy policy wording for this site's settings, as
 * plain sentences. Shown on the Retention tab and in Settings, Privacy.
 *
 * @return string[] Paragraphs.
 */
function gre_privacy_paragraphs() {
	$months = (int) gre_setting( 'retention_months' );
	$days   = max( 1, (int) gre_setting( 'spam_days' ) );
	$out    = array(
		__( 'When you send a message through our contact form, we keep your name, email address and message (and your phone number or any other details you choose to add) so we can reply to you. We also note which page you sent it from.', 'grow-enquiries' ),
		$months
			/* translators: %d: months */
			? sprintf( __( 'We delete enquiries automatically after %d months, or sooner if you ask us to.', 'grow-enquiries' ), $months )
			: __( 'We keep enquiries until you ask us to delete them.', 'grow-enquiries' ),
		/* translators: %d: days */
		sprintf( __( 'Messages our spam filter holds back are deleted after %d days. To stop repeat messages, your IP address is turned into a one-way code and kept for an hour; it is never stored with your message.', 'grow-enquiries' ), $days ),
	);
	if ( gre_turnstile_on() ) {
		$out[] = __( 'The form uses Cloudflare Turnstile to tell people from bots. Cloudflare may process your IP address and browser details for this; see Cloudflare\'s privacy policy.', 'grow-enquiries' );
	}
	$out[] = __( 'Your message is emailed to us through our email delivery service. We never sell or share your details, and you can ask us for a copy of what we hold about you, or for it to be deleted, at any time.', 'grow-enquiries' );
	return $out;
}

/**
 * Add the wording to WordPress's privacy policy guide.
 */
function gre_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$html  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Grow Enquiries keeps every message sent through the enquiry form in a private log in wp-admin. This wording follows the retention settings under Enquiries, Settings, Retention and digest; it updates when they change.', 'grow-enquiries' ) . '</p>';
	$html .= '<strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'grow-enquiries' ) . ' </strong>';
	foreach ( gre_privacy_paragraphs() as $p ) {
		$html .= '<p>' . esc_html( $p ) . '</p>';
	}
	wp_add_privacy_policy_content( __( 'Grow Enquiries', 'grow-enquiries' ), wp_kses_post( $html ) );
}
add_action( 'admin_init', 'gre_privacy_policy_content' );
