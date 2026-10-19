<?php
/**
 * Plugin Name:       Grow Enquiries
 * Description:       Enquiry forms with a private log, cache-safe spam protection and shadow-accept quarantine. Forms by template tag or [enquiry_form] shortcode; nothing ever prints the recipient's address.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Grow Creative
 * Author URI:        https://growcreative.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       grow-enquiries
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

const GRE_VERSION   = '0.1.0';
const GRE_FILE      = __FILE__;
const GRE_POST_TYPE = 'grow_enquiry';
const GRE_CAP       = 'manage_enquiries';
const GRE_OPTION    = 'grow_enquiries_settings';

define( 'GRE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRE_URL', plugin_dir_url( __FILE__ ) );

require GRE_DIR . 'includes/settings.php';
require GRE_DIR . 'includes/spam.php';
require GRE_DIR . 'includes/post-type.php';
require GRE_DIR . 'includes/mail.php';
require GRE_DIR . 'includes/form.php';
require GRE_DIR . 'includes/cron.php';

if ( is_admin() ) {
	require GRE_DIR . 'includes/admin-list.php';
	require GRE_DIR . 'includes/admin-view.php';
	require GRE_DIR . 'includes/admin-settings.php';
}

/**
 * Activation: capability for administrators and editors, cron jobs.
 */
function gre_activate() {
	gre_add_caps();
	gre_schedule();
	update_option( 'grow_enquiries_version', GRE_VERSION, false );
}
register_activation_hook( __FILE__, 'gre_activate' );

/**
 * Deactivation: stop the cron jobs. Enquiries and settings stay.
 */
function gre_deactivate() {
	wp_clear_scheduled_hook( 'gre_daily' );
}
register_deactivation_hook( __FILE__, 'gre_deactivate' );

/**
 * Who can read enquiries.
 */
function gre_add_caps() {
	foreach ( (array) apply_filters( 'grow_enquiry_roles', array( 'administrator', 'editor' ) ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role && ! $role->has_cap( GRE_CAP ) ) {
			$role->add_cap( GRE_CAP );
		}
	}
}

/**
 * Run any one-off upgrade steps once after an update. No hand work on
 * the server, ever.
 */
function gre_maybe_upgrade() {
	$stored = get_option( 'grow_enquiries_version', '' );
	if ( GRE_VERSION === $stored ) {
		return;
	}
	gre_add_caps();
	gre_schedule();
	update_option( 'grow_enquiries_version', GRE_VERSION, false );
}
add_action( 'init', 'gre_maybe_upgrade', 1 );
