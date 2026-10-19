<?php
/**
 * A small Dashboard widget: unread enquiries and spam held this month,
 * each linking to its view, plus a red line for any email that hasn't
 * gone.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register it for anyone who can read enquiries.
 */
function gre_dashboard_setup() {
	if ( current_user_can( GRE_CAP ) ) {
		wp_add_dashboard_widget( 'gre_dashboard', __( 'Enquiries', 'grow-enquiries' ), 'gre_render_dashboard' );
	}
}
add_action( 'wp_dashboard_setup', 'gre_dashboard_setup' );

/**
 * The widget.
 */
function gre_render_dashboard() {
	$base   = admin_url( 'edit.php?post_type=' . GRE_POST_TYPE );
	$unread = gre_unread_count();
	$month  = ( new DateTimeImmutable( 'first day of this month', wp_timezone() ) )->setTime( 0, 0 )->getTimestamp();
	$held   = gre_view_count( 'spam', $month );
	$failed = gre_failed_count();
	?>
	<ul class="gre-dash">
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'gre_view', 'inbox', $base ) ); ?>">
				<strong><?php echo (int) $unread; ?></strong>
				<?php echo esc_html( _n( 'unread', 'unread', $unread, 'grow-enquiries' ) ); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'gre_view', 'spam', $base ) ); ?>">
				<strong><?php echo (int) $held; ?></strong>
				<?php echo esc_html( _n( 'held as spam this month', 'held as spam this month', $held, 'grow-enquiries' ) ); ?>
			</a>
		</li>
		<?php if ( $failed ) : ?>
			<li class="gre-dash__bad">
				<a href="<?php echo esc_url( add_query_arg( 'gre_view', 'failed', $base ) ); ?>">
					<strong><?php echo (int) $failed; ?></strong>
					<?php echo esc_html( _n( 'not emailed', 'not emailed', $failed, 'grow-enquiries' ) ); ?>
				</a>
			</li>
		<?php endif; ?>
	</ul>
	<style>
		.gre-dash{display:flex;flex-wrap:wrap;gap:8px;margin:0}
		.gre-dash li{flex:1 1 120px;margin:0}
		.gre-dash a{display:block;padding:10px 12px;border:1px solid #dcdcde;border-radius:4px;text-decoration:none;color:#50575e}
		.gre-dash a:hover{border-color:#2271b1}
		.gre-dash strong{display:block;font-size:24px;line-height:1.2;color:#1d2327}
		.gre-dash__bad a,.gre-dash__bad strong{color:#d63638;border-color:#d63638}
	</style>
	<?php
}
