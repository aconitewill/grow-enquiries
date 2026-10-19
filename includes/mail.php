<?php
/**
 * Email. Reply-To is the visitor, so the recipient just hits reply. The
 * recipient address is only ever used here, never printed on a page.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/**
 * The site's domain, for subjects.
 *
 * @return string
 */
function gre_domain() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	return $host ? preg_replace( '/^www\./', '', $host ) : get_bloginfo( 'name' );
}

/**
 * The subject line from the pattern.
 *
 * @param int $id Enquiry.
 * @return string
 */
function gre_subject( $id ) {
	$label = (string) gre_meta( $id, 'about_label' );
	$topic = '' !== $label ? 'Enquiry about ' . $label : 'General enquiry';
	return trim(
		strtr(
			(string) gre_setting( 'subject' ),
			array(
				'{domain}' => gre_domain(),
				'{site}'   => get_bloginfo( 'name' ),
				'{name}'   => (string) gre_meta( $id, 'name' ),
				'{about}'  => $label,
				'{topic}'  => $topic,
			)
		)
	);
}

/**
 * The details as label => value, shared by both email formats and the
 * admin view.
 *
 * @param int $id Enquiry.
 * @return array
 */
function gre_details( $id ) {
	$rows = array(
		'Name'  => (string) gre_meta( $id, 'name' ),
		'Email' => (string) gre_meta( $id, 'email' ),
	);
	if ( gre_meta( $id, 'phone' ) ) {
		$rows['Phone'] = (string) gre_meta( $id, 'phone' );
	}
	if ( gre_meta( $id, 'about_label' ) ) {
		$rows['About'] = (string) gre_meta( $id, 'about_label' );
	}
	foreach ( (array) gre_meta( $id, 'extras' ) as $label => $value ) {
		if ( '' !== (string) $value ) {
			$rows[ $label ] = (string) $value;
		}
	}
	if ( gre_meta( $id, 'page' ) ) {
		$rows['Sent from'] = (string) gre_meta( $id, 'page' );
	}
	return $rows;
}

/**
 * Email an enquiry to the recipients. Used on submit and on release.
 *
 * @param int $id Enquiry.
 * @return bool
 */
function gre_send( $id ) {
	$name    = (string) gre_meta( $id, 'name' );
	$email   = (string) gre_meta( $id, 'email' );
	$message = (string) gre_meta( $id, 'message' );
	$view    = gre_view_url( $id );
	$details = gre_details( $id );

	$headers = array();
	if ( is_email( $email ) ) {
		$headers[] = sprintf( 'Reply-To: %s <%s>', str_replace( array( '<', '>', "\r", "\n", '"' ), '', $name ), $email );
	}
	if ( gre_setting( 'from_email' ) && is_email( gre_setting( 'from_email' ) ) ) {
		$from      = gre_setting( 'from_name' ) ? gre_setting( 'from_name' ) : get_bloginfo( 'name' );
		$headers[] = sprintf( 'From: %s <%s>', str_replace( array( '<', '>', "\r", "\n", '"' ), '', $from ), gre_setting( 'from_email' ) );
	}

	if ( 'plain' === gre_setting( 'format' ) ) {
		$body = '';
		foreach ( $details as $label => $value ) {
			$body .= $label . ': ' . $value . "\n";
		}
		$body .= "\n" . $message . "\n\n--\nReply to this email to answer " . $name . ".\nIn the log: " . $view . "\n";
	} else {
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$body      = gre_html_email( $name, $details, $message, $view );
	}

	return (bool) wp_mail( gre_recipients(), gre_subject( $id ), $body, $headers );
}

/**
 * Email an enquiry and record how it went: sent, or the error and the
 * time of the try, for the hourly retry. Success clears any flag.
 *
 * @param int $id Enquiry.
 * @return bool
 */
function gre_deliver( $id ) {
	$GLOBALS['gre_mail_error'] = '';
	add_action( 'wp_mail_failed', 'gre_catch_mail_error' );
	$ok = gre_send( $id );
	remove_action( 'wp_mail_failed', 'gre_catch_mail_error' );

	update_post_meta( $id, '_gre_sent', $ok ? 1 : 0 );
	update_post_meta( $id, '_gre_last_try', time() );
	if ( $ok ) {
		delete_post_meta( $id, '_gre_mail_error' );
		delete_post_meta( $id, '_gre_flagged' );
	} else {
		$error = '' !== $GLOBALS['gre_mail_error'] ? $GLOBALS['gre_mail_error'] : __( 'The mail plugin reported a failure.', 'grow-enquiries' );
		update_post_meta( $id, '_gre_mail_error', mb_substr( $error, 0, 300 ) );
	}
	return $ok;
}

/**
 * Keep WordPress's reason for a failed email.
 *
 * @param WP_Error $error Error.
 */
function gre_catch_mail_error( $error ) {
	if ( is_wp_error( $error ) ) {
		$GLOBALS['gre_mail_error'] = sanitize_text_field( $error->get_error_message() );
	}
}

/**
 * The HTML email: one column, the accent colour, the message in a box.
 *
 * @param string $name    Sender.
 * @param array  $details Label => value.
 * @param string $message Message.
 * @param string $view    Link to the enquiry in wp-admin.
 * @return string
 */
function gre_html_email( $name, $details, $message, $view ) {
	$accent = sanitize_hex_color( (string) gre_setting( 'accent' ) );
	$accent = $accent ? $accent : '#2271b1';
	$font   = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif';

	$rows = '';
	foreach ( $details as $label => $value ) {
		$cell = 'Email' === $label && is_email( $value )
			? '<a href="mailto:' . esc_attr( $value ) . '" style="color:' . $accent . ';">' . esc_html( $value ) . '</a>'
			: esc_html( $value );
		$rows .= '<tr><td style="padding:5px 16px 5px 0;font-weight:bold;vertical-align:top;white-space:nowrap;">' . esc_html( $label ) . '</td><td style="padding:5px 0;">' . $cell . '</td></tr>';
	}

	$out  = '<div style="font-family:' . $font . ';color:#222;font-size:15px;line-height:1.6;max-width:580px;">';
	$out .= '<h2 style="margin:0 0 4px;font-size:19px;color:' . $accent . ';">New enquiry</h2>';
	$out .= '<p style="margin:0 0 20px;color:#777;font-size:13px;">From the form on ' . esc_html( gre_domain() ) . '.</p>';
	$out .= '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $rows . '</table>';
	$out .= '<p style="margin:22px 0 8px;font-weight:bold;">Message</p>';
	$out .= '<div style="background:#f6f6f6;border-left:4px solid ' . $accent . ';padding:14px 18px;">' . nl2br( esc_html( $message ) ) . '</div>';
	$out .= '<p style="margin:24px 0 0;color:#777;font-size:13px;">Reply to this email to answer ' . esc_html( $name ) . ' directly. <a href="' . esc_url( $view ) . '" style="color:#777;">Open in the log</a>.</p>';
	$out .= '</div>';
	return $out;
}

/**
 * Which mail plugin is delivering, for the settings screen.
 *
 * @return string Empty when WordPress's own mail is in use.
 */
function gre_mailer_name() {
	try {
		$file = ( new ReflectionFunction( 'wp_mail' ) )->getFileName();
	} catch ( ReflectionException $e ) {
		return '';
	}
	if ( false !== strpos( $file, WPINC ) ) {
		return '';
	}
	$rel   = plugin_basename( $file );
	$slug  = strtok( $rel, '/' );
	$names = array(
		'postmark-approved-wordpress-plugin' => 'Postmark',
		'wp-mail-smtp'                       => 'WP Mail SMTP',
		'fluent-smtp'                        => 'FluentSMTP',
		'post-smtp'                          => 'Post SMTP',
	);
	return isset( $names[ $slug ] ) ? $names[ $slug ] : $slug;
}
