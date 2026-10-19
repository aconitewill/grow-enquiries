<?php
/**
 * Enquiries, Settings: tabs for Delivery, Spam protection, Retention,
 * Form and Tools. Administrators only.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * The fields, per tab. Rendered and sanitised from this one list.
 *
 * @return array tab => array( label, fields )
 */
function gre_settings_tabs() {
	$packs = array();
	foreach ( gre_sector_packs() as $slug => $pack ) {
		$packs[ $slug ] = $pack['label'];
	}
	return array(
		'delivery' => array(
			'label'  => __( 'Delivery', 'grow-enquiries' ),
			'fields' => array(
				'recipients' => array( 'textarea', __( 'Send enquiries to', 'grow-enquiries' ), __( 'One address per line. Never shown on the site. Blank sends to the site admin email.', 'grow-enquiries' ) ),
				'from_name'  => array( 'text', __( 'From name', 'grow-enquiries' ), __( 'Blank leaves it to the mail plugin, which is what you want with Postmark.', 'grow-enquiries' ) ),
				'from_email' => array( 'email', __( 'From address', 'grow-enquiries' ), __( 'Blank leaves it to the mail plugin. If set, it must be a sender the mail service has verified.', 'grow-enquiries' ) ),
				'subject'    => array( 'text', __( 'Subject', 'grow-enquiries' ), __( 'Placeholders: {domain} {site} {name} {about} {topic}. {topic} is "Enquiry about X" or "General enquiry".', 'grow-enquiries' ) ),
				'format'     => array( 'select', __( 'Email format', 'grow-enquiries' ), '', array( 'html' => 'HTML', 'plain' => 'Plain text' ) ),
				'accent'     => array( 'color', __( 'Accent colour', 'grow-enquiries' ), __( 'Used in the HTML email.', 'grow-enquiries' ) ),
			),
		),
		'spam'     => array(
			'label'  => __( 'Spam protection', 'grow-enquiries' ),
			'fields' => array(
				'turnstile_site'    => array( 'text', __( 'Turnstile site key', 'grow-enquiries' ), __( 'Cloudflare Turnstile switches on when both keys are filled in. Or set GRE_TURNSTILE_SITE_KEY and GRE_TURNSTILE_SECRET_KEY in wp-config.php.', 'grow-enquiries' ) ),
				'turnstile_secret'  => array( 'password', __( 'Turnstile secret key', 'grow-enquiries' ), '' ),
				'packs'             => array( 'checkboxes', __( 'Sector packs', 'grow-enquiries' ), __( 'Extra pitch wording common in a particular trade.', 'grow-enquiries' ), $packs ),
				'extra_terms'       => array( 'textarea', __( 'Extra terms', 'grow-enquiries' ), __( 'Plain words, one per line. Add "| 2" to set a weight (default 3, which holds a message on its own).', 'grow-enquiries' ) ),
				'ignored_terms'     => array( 'textarea', __( 'Ignored terms', 'grow-enquiries' ), __( 'Built-in terms that are genuine for this business, one per line, as named in the list below.', 'grow-enquiries' ) ),
				'links_suspicious'  => array( 'checkbox', __( 'Treat links as suspicious', 'grow-enquiries' ), __( 'Each link adds 2. One link alone is let through.', 'grow-enquiries' ) ),
				'blocked'           => array( 'textarea', __( 'Blocked senders', 'grow-enquiries' ), __( 'Addresses, or @domain.com for a whole domain. "Block sender" on an enquiry adds to this.', 'grow-enquiries' ) ),
				'threshold'         => array( 'number', __( 'Hold at score', 'grow-enquiries' ), __( 'Advanced. Default 3.', 'grow-enquiries' ) ),
				'min_seconds'       => array( 'number', __( 'Minimum seconds on the page', 'grow-enquiries' ), __( 'Advanced. Faster than this is a bot. Default 5.', 'grow-enquiries' ) ),
				'rate_limit'        => array( 'number', __( 'Messages per visitor per hour', 'grow-enquiries' ), __( 'Advanced. Default 5.', 'grow-enquiries' ) ),
				'behind_cloudflare' => array( 'checkbox', __( 'Site is behind Cloudflare', 'grow-enquiries' ), __( 'Trust the CF-Connecting-IP header for the rate limit. On for Kinsta sites, off elsewhere.', 'grow-enquiries' ) ),
			),
		),
		'retention' => array(
			'label'  => __( 'Retention and digest', 'grow-enquiries' ),
			'fields' => array(
				'spam_days'        => array( 'number', __( 'Delete held spam after (days)', 'grow-enquiries' ), __( 'Default 60.', 'grow-enquiries' ) ),
				'retention_months' => array( 'number', __( 'Delete genuine enquiries after (months)', 'grow-enquiries' ), __( '0 keeps them forever. Default 24. Say so in the privacy policy.', 'grow-enquiries' ) ),
				'digest'           => array( 'select', __( 'Digest of held spam', 'grow-enquiries' ), __( 'An email listing what the spam filter held, and any enquiry whose email failed, so nothing is deleted unseen. Weekly comes on Mondays, monthly on the 1st. Only sent when there is something in it.', 'grow-enquiries' ), array( 'monthly' => __( 'Monthly', 'grow-enquiries' ), 'weekly' => __( 'Weekly', 'grow-enquiries' ), 'off' => __( 'Off', 'grow-enquiries' ) ) ),
				'digest_to'        => array( 'text', __( 'Send the digest to', 'grow-enquiries' ), __( 'Blank sends it to the enquiry recipients. Several addresses: separate them with commas.', 'grow-enquiries' ) ),
			),
		),
		'form'     => array(
			'label'  => __( 'Form', 'grow-enquiries' ),
			'fields' => array(
				'thanks_heading' => array( 'text', __( 'Thank-you heading', 'grow-enquiries' ), '' ),
				'thanks_text'    => array( 'text', __( 'Thank-you line', 'grow-enquiries' ), '' ),
				'privacy_note'   => array( 'text', __( 'Privacy note under the form', 'grow-enquiries' ), '' ),
				'button'         => array( 'text', __( 'Button', 'grow-enquiries' ), '' ),
				'phone'          => array( 'checkbox', __( 'Ask for a phone number (optional field)', 'grow-enquiries' ), '' ),
				'stylesheet'     => array( 'checkbox', __( "Use the plugin's stylesheet", 'grow-enquiries' ), __( 'Off for themes that style the form themselves.', 'grow-enquiries' ) ),
			),
		),
		'tools'    => array(
			'label'  => __( 'Tools', 'grow-enquiries' ),
			'fields' => array(),
		),
	);
}

/**
 * The Settings page.
 */
function gre_settings_menu() {
	add_submenu_page( 'edit.php?post_type=' . GRE_POST_TYPE, __( 'Enquiry settings', 'grow-enquiries' ), __( 'Settings', 'grow-enquiries' ), 'manage_options', 'grow-enquiries-settings', 'gre_render_settings' );
}
add_action( 'admin_menu', 'gre_settings_menu' );

/**
 * Save one tab, merged into the rest.
 */
function gre_save_settings() {
	check_admin_referer( 'gre_settings' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'grow-enquiries' ) );
	}
	$tabs = gre_settings_tabs();
	$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
	if ( ! isset( $tabs[ $tab ] ) ) {
		wp_die( esc_html__( 'Unknown tab.', 'grow-enquiries' ) );
	}
	$saved = (array) get_option( GRE_OPTION, array() );
	$in    = isset( $_POST['gre'] ) && is_array( $_POST['gre'] ) ? wp_unslash( $_POST['gre'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per type below.

	foreach ( $tabs[ $tab ]['fields'] as $key => $f ) {
		$type = $f[0];
		$v    = isset( $in[ $key ] ) ? $in[ $key ] : null;
		switch ( $type ) {
			case 'checkbox':
				$saved[ $key ] = $v ? 1 : 0;
				break;
			case 'checkboxes':
				$saved[ $key ] = array_values( array_intersect( array_keys( $f[3] ), array_map( 'sanitize_key', (array) $v ) ) );
				break;
			case 'number':
				$saved[ $key ] = max( 0, (int) $v );
				break;
			case 'email':
				$saved[ $key ] = sanitize_email( (string) $v );
				break;
			case 'color':
				$saved[ $key ] = sanitize_hex_color( (string) $v ) ? sanitize_hex_color( (string) $v ) : '#2271b1';
				break;
			case 'select':
				$saved[ $key ] = array_key_exists( (string) $v, $f[3] ) ? (string) $v : key( $f[3] );
				break;
			case 'textarea':
				$saved[ $key ] = sanitize_textarea_field( (string) $v );
				break;
			case 'password':
				// Blank keeps the stored secret: it's never printed back.
				if ( '' !== trim( (string) $v ) ) {
					$saved[ $key ] = sanitize_text_field( (string) $v );
				}
				break;
			default:
				$saved[ $key ] = sanitize_text_field( (string) $v );
		}
	}

	update_option( GRE_OPTION, $saved );
	gre_settings_flush();
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . GRE_POST_TYPE . '&page=grow-enquiries-settings&tab=' . $tab . '&saved=1' ) );
	exit;
}
add_action( 'admin_post_gre_save_settings', 'gre_save_settings' );

/**
 * Send a test email.
 */
function gre_test_email() {
	check_admin_referer( 'gre_test_email' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'grow-enquiries' ) );
	}
	$ok = wp_mail(
		gre_recipients(),
		'[' . gre_domain() . '] ' . __( 'Test from Grow Enquiries', 'grow-enquiries' ),
		__( "This is a test from Enquiries, Settings, Tools. If it arrived, enquiries will too.\n", 'grow-enquiries' )
	);
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . GRE_POST_TYPE . '&page=grow-enquiries-settings&tab=tools&test=' . ( $ok ? 'ok' : 'fail' ) ) );
	exit;
}
add_action( 'admin_post_gre_test_email', 'gre_test_email' );

/**
 * Send what the next digest would hold, now, without moving the period on.
 */
function gre_digest_preview() {
	check_admin_referer( 'gre_digest_preview' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'grow-enquiries' ) );
	}
	$result = gre_send_digest( gre_digest_last(), true );
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . GRE_POST_TYPE . '&page=grow-enquiries-settings&tab=tools&digest=' . $result ) );
	exit;
}
add_action( 'admin_post_gre_digest_preview', 'gre_digest_preview' );

/**
 * Render one field.
 *
 * @param string $key Key.
 * @param array  $f   Definition.
 */
function gre_render_field( $key, $f ) {
	$type  = $f[0];
	$value = gre_setting( $key );
	$name  = 'gre[' . $key . ']';
	$id    = 'gre-' . $key;
	switch ( $type ) {
		case 'textarea':
			printf( '<textarea id="%s" name="%s" rows="4" class="large-text code">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
			break;
		case 'checkbox':
			printf( '<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ), esc_html( $f[1] ) );
			break;
		case 'checkboxes':
			foreach ( $f[3] as $opt => $label ) {
				printf( '<label style="display:block"><input type="checkbox" name="%s[]" value="%s" %s> %s</label>', esc_attr( $name ), esc_attr( $opt ), checked( in_array( $opt, (array) $value, true ), true, false ), esc_html( $label ) );
			}
			break;
		case 'select':
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( $f[3] as $opt => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
			}
			echo '</select>';
			break;
		case 'number':
			printf( '<input type="number" min="0" id="%s" name="%s" value="%d" class="small-text">', esc_attr( $id ), esc_attr( $name ), (int) $value );
			break;
		case 'password':
			$shown = (string) $value ? str_repeat( '•', 8 ) . substr( (string) $value, -4 ) : '';
			printf( '<input type="password" id="%s" name="%s" value="" class="regular-text" autocomplete="new-password" placeholder="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $shown ? $shown . ' (leave blank to keep)' : '' ) );
			break;
		case 'color':
			printf( '<input type="color" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			break;
		case 'email':
			printf( '<input type="email" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			break;
		default:
			printf( '<input type="text" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
	}
	if ( 'checkbox' !== $type && ! empty( $f[2] ) ) {
		echo '<p class="description">' . esc_html( $f[2] ) . '</p>';
	} elseif ( 'checkbox' === $type && ! empty( $f[2] ) ) {
		echo '<p class="description">' . esc_html( $f[2] ) . '</p>';
	}
}

/**
 * The page.
 */
function gre_render_settings() {
	$tabs = gre_settings_tabs();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'delivery'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'delivery';
	$base = admin_url( 'edit.php?post_type=' . GRE_POST_TYPE . '&page=grow-enquiries-settings' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Enquiry settings', 'grow-enquiries' ); ?></h1>

		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'grow-enquiries' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! trim( (string) gre_setting( 'recipients' ) ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'No recipients set, so enquiries go to the site admin email. Set them under Delivery.', 'grow-enquiries' ); ?></p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $t ) : ?>
				<a class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base . '&tab=' . $slug ); ?>"><?php echo esc_html( $t['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'tools' === $tab ) : ?>
			<?php gre_render_tools(); ?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gre_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'gre_settings' ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $tabs[ $tab ]['fields'] as $key => $f ) : ?>
						<tr>
							<th scope="row"><?php echo 'checkbox' === $f[0] ? '' : '<label for="gre-' . esc_attr( $key ) . '">' . esc_html( $f[1] ) . '</label>'; ?></th>
							<td><?php gre_render_field( $key, $f ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php if ( 'spam' === $tab ) : ?>
					<h2><?php esc_html_e( 'Built-in terms', 'grow-enquiries' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Copy a name into Ignored terms to switch it off for this site. Weight 3 holds a message on its own; weight 2 needs a second signal.', 'grow-enquiries' ); ?></p>
					<p style="columns:3 220px;max-width:900px">
						<?php foreach ( gre_base_terms() as $label => $t ) : ?>
							<span style="display:block"><code><?php echo esc_html( $label ); ?></code> <?php echo (int) $t[1]; ?></span>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
				<?php if ( 'retention' === $tab ) : ?>
					<h2><?php esc_html_e( 'Suggested privacy policy wording', 'grow-enquiries' ); ?></h2>
					<p class="description" style="max-width:700px">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: link to Settings, Privacy */
								__( 'Also in WordPress\'s privacy policy guide (%s). It follows these settings. Requests to see or delete someone\'s enquiries: Tools, Export Personal Data and Erase Personal Data, by email address.', 'grow-enquiries' ),
								'<a href="' . esc_url( admin_url( 'options-privacy.php?tab=policyguide' ) ) . '">' . esc_html__( 'Settings, Privacy, Policy guide', 'grow-enquiries' ) . '</a>'
							),
							array( 'a' => array( 'href' => array() ) )
						);
						?>
					</p>
					<div style="max-width:700px;background:#fff;border:1px solid #dcdcde;padding:4px 16px">
						<?php foreach ( gre_privacy_paragraphs() as $para ) : ?>
							<p><?php echo esc_html( $para ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php submit_button(); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The Tools tab: test email, test a message, health.
 */
function gre_render_tools() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$test   = isset( $_GET['test'] ) ? sanitize_key( wp_unslash( $_GET['test'] ) ) : '';
	$digest = isset( $_GET['digest'] ) ? sanitize_key( wp_unslash( $_GET['digest'] ) ) : '';
	// phpcs:enable
	$sample = '';
	$result = null;
	if ( isset( $_POST['gre_sample'] ) && check_admin_referer( 'gre_sample' ) ) {
		$sample = sanitize_textarea_field( wp_unslash( $_POST['gre_sample'] ) );
		$result = gre_spam_score( '', $sample );
	}
	$mailer = gre_mailer_name();
	$next   = wp_next_scheduled( 'gre_daily' );
	$hourly = wp_next_scheduled( 'gre_hourly' );
	$last   = (int) get_option( 'grow_enquiries_last_purge', 0 );
	$sent   = (int) get_option( 'grow_enquiries_last_digest_sent', 0 );
	$queue  = count( gre_retry_queue() );
	$fail   = gre_failed_count();
	$freq   = (string) gre_setting( 'digest' );
	?>
	<h2><?php esc_html_e( 'Send a test email', 'grow-enquiries' ); ?></h2>
	<?php if ( 'ok' === $test ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Sent. Check the inbox.', 'grow-enquiries' ); ?></p></div>
	<?php elseif ( 'fail' === $test ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'WordPress could not send it. Check the mail plugin.', 'grow-enquiries' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="gre_test_email">
		<?php wp_nonce_field( 'gre_test_email' ); ?>
		<p><?php echo esc_html( sprintf( /* translators: %s: addresses */ __( 'Goes to: %s', 'grow-enquiries' ), implode( ', ', gre_recipients() ) ) ); ?></p>
		<?php submit_button( __( 'Send test email', 'grow-enquiries' ), 'secondary', 'submit', false ); ?>
	</form>

	<h2><?php esc_html_e( 'Digest', 'grow-enquiries' ); ?></h2>
	<?php if ( 'sent' === $digest ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Preview sent. Check the inbox.', 'grow-enquiries' ); ?></p></div>
	<?php elseif ( 'empty' === $digest ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'Nothing to send: nothing held since the last digest, and no failed emails.', 'grow-enquiries' ); ?></p></div>
	<?php elseif ( 'failed' === $digest ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'WordPress could not send it. Check the mail plugin.', 'grow-enquiries' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="gre_digest_preview">
		<?php wp_nonce_field( 'gre_digest_preview' ); ?>
		<p><?php echo esc_html( sprintf( /* translators: %s: addresses */ __( 'Sends what the next digest would hold, now, to %s. The next real digest still covers the same period.', 'grow-enquiries' ), implode( ', ', gre_digest_recipients() ) ) ); ?></p>
		<?php submit_button( __( 'Send a preview of the digest', 'grow-enquiries' ), 'secondary', 'submit', false ); ?>
	</form>

	<h2><?php esc_html_e( 'Export', 'grow-enquiries' ); ?></h2>
	<p><?php echo wp_kses( sprintf( /* translators: %s: link */ __( 'Export CSV is above the list on %s: it exports the view you are looking at (Inbox, Spam, Not emailed or Bin), with an optional date range.', 'grow-enquiries' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . GRE_POST_TYPE ) ) . '">' . esc_html__( 'Enquiries', 'grow-enquiries' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) ); ?></p>

	<h2><?php esc_html_e( 'Test a message', 'grow-enquiries' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Paste any message to see how the spam filter scores it with this site\'s settings. Nothing is saved or sent.', 'grow-enquiries' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'gre_sample' ); ?>
		<textarea name="gre_sample" rows="6" class="large-text"><?php echo esc_textarea( $sample ); ?></textarea>
		<?php submit_button( __( 'Score it', 'grow-enquiries' ), 'secondary', 'submit', false ); ?>
	</form>
	<?php if ( null !== $result ) : ?>
		<?php $held = $result['score'] >= (int) gre_setting( 'threshold' ); ?>
		<div class="notice notice-<?php echo $held ? 'warning' : 'success'; ?> inline">
			<p>
				<strong><?php echo $held ? esc_html__( 'Would be held as spam.', 'grow-enquiries' ) : esc_html__( 'Would be let through.', 'grow-enquiries' ); ?></strong>
				<?php echo esc_html( sprintf( /* translators: 1: score, 2: threshold */ __( 'Score %1$d (holds at %2$d).', 'grow-enquiries' ), $result['score'], (int) gre_setting( 'threshold' ) ) ); ?>
				<?php if ( $result['hits'] ) : ?>
					<?php echo esc_html( __( 'Matched:', 'grow-enquiries' ) . ' ' . implode( ', ', $result['hits'] ) ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Health', 'grow-enquiries' ); ?></h2>
	<table class="widefat striped" style="max-width:700px">
		<tr><td><?php esc_html_e( 'Version', 'grow-enquiries' ); ?></td><td><?php echo esc_html( GRE_VERSION ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Mail', 'grow-enquiries' ); ?></td><td><?php echo $mailer ? esc_html( sprintf( /* translators: %s: plugin */ __( 'Delivered by %s', 'grow-enquiries' ), $mailer ) ) : '<strong style="color:#d63638">' . esc_html__( 'No mail plugin found: WordPress is sending mail itself, which often lands in spam. Install Postmark.', 'grow-enquiries' ) . '</strong>'; ?></td></tr>
		<tr><td><?php esc_html_e( 'Turnstile', 'grow-enquiries' ); ?></td><td><?php echo gre_turnstile_on() ? esc_html__( 'On', 'grow-enquiries' ) : esc_html__( 'Off (honeypot, time and content checks still run)', 'grow-enquiries' ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Daily clean-up', 'grow-enquiries' ); ?></td><td><?php echo $next ? esc_html( sprintf( /* translators: %s: date */ __( 'Next run %s', 'grow-enquiries' ), wp_date( 'j F Y, H:i', $next ) ) ) : '<strong style="color:#d63638">' . esc_html__( 'Not scheduled. Deactivate and reactivate the plugin.', 'grow-enquiries' ) . '</strong>'; ?><?php echo $last ? esc_html( sprintf( /* translators: %s: date */ __( '. Last run %s', 'grow-enquiries' ), wp_date( 'j F Y, H:i', $last ) ) ) : ''; ?></td></tr>
		<tr><td><?php esc_html_e( 'Email retries', 'grow-enquiries' ); ?></td><td><?php echo $hourly ? esc_html( sprintf( /* translators: 1: date, 2: number being retried, 3: max tries */ __( 'Next run %1$s. %2$d being retried (up to %3$d times, hourly).', 'grow-enquiries' ), wp_date( 'j F Y, H:i', $hourly ), $queue, GRE_MAX_TRIES ) ) : '<strong style="color:#d63638">' . esc_html__( 'Not scheduled. Deactivate and reactivate the plugin.', 'grow-enquiries' ) . '</strong>'; ?></td></tr>
		<tr><td><?php esc_html_e( 'Digest', 'grow-enquiries' ); ?></td><td>
			<?php
			if ( 'off' === $freq ) {
				esc_html_e( 'Off', 'grow-enquiries' );
			} else {
				echo esc_html( 'weekly' === $freq ? __( 'Weekly, on Mondays', 'grow-enquiries' ) : __( 'Monthly, on the 1st', 'grow-enquiries' ) );
				echo esc_html( $sent ? sprintf( /* translators: %s: date */ __( '. Last sent %s', 'grow-enquiries' ), wp_date( 'j F Y, H:i', $sent ) ) : __( '. None sent yet', 'grow-enquiries' ) );
			}
			?>
		</td></tr>
		<tr><td><?php esc_html_e( 'Updates', 'grow-enquiries' ); ?></td><td><?php echo isset( $GLOBALS['gre_update_checker'] ) ? esc_html__( 'Checked against GitHub releases (aconitewill/grow-enquiries), shown in Dashboard, Updates', 'grow-enquiries' ) : esc_html__( 'Off (a git checkout, or GRE_DISABLE_UPDATES is set)', 'grow-enquiries' ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Enquiries', 'grow-enquiries' ); ?></td><td><?php echo esc_html( sprintf( /* translators: 1: inbox, 2: spam, 3: not emailed */ __( '%1$d in the inbox, %2$d held as spam, %3$d not emailed', 'grow-enquiries' ), gre_count( false ), gre_count( true ), $fail ) ); ?></td></tr>
	</table>
	<?php
}
