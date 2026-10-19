<?php
/**
 * The form: template tag, shortcode, rendering and the submission handler.
 *
 * Posts to admin-post.php, so it works for logged-out visitors and with
 * full-page caching. No nonce: a nonce does nothing for a public form and
 * goes stale on a cached page. The JS token and the time check do that job.
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================
   Template tag and shortcode
   ========================================================= */

/**
 * Print a form.
 *
 * @param array $args {
 *     @type string $form_id Which form, when there are several on a page.
 *     @type int    $about   A post the enquiry is about (locks an "Enquiring about" field).
 *     @type string $heading Optional heading above the form.
 *     @type string $intro   Optional line under the heading.
 *     @type string $button  Button text.
 * }
 */
function grow_enquiry_form( $args = array() ) {
	echo gre_render_form( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
}

/**
 * [enquiry_form form_id="" about="" heading="" intro="" button=""]
 *
 * @param array $atts Attributes.
 * @return string
 */
function gre_shortcode( $atts ) {
	return gre_render_form( shortcode_atts( gre_form_defaults(), (array) $atts, 'enquiry_form' ) );
}
add_shortcode( 'enquiry_form', 'gre_shortcode' );

/**
 * @return array
 */
function gre_form_defaults() {
	return array(
		'form_id' => 'general',
		'about'   => 0,
		'heading' => '',
		'intro'   => '',
		'button'  => '',
	);
}

/**
 * Extra fields a theme has added with the grow_enquiry_fields filter.
 *
 * Each: key => array(
 *     'label'    => 'What is it about?',
 *     'type'     => 'text' | 'select' | 'date',
 *     'options'  => array( value => label ),   // select only
 *     'required' => false,
 *     'about'    => false,   // true: the chosen option names the enquiry (subject line, log)
 *     'prefill'  => '',      // a URL query parameter that pre-selects it, read in the browser
 *     'placeholder' => '',
 * )
 *
 * @param string $form_id Form.
 * @return array
 */
function gre_extra_fields( $form_id ) {
	$fields = (array) apply_filters( 'grow_enquiry_fields', array(), $form_id );
	$clean  = array();
	foreach ( $fields as $key => $f ) {
		$key = sanitize_key( $key );
		if ( '' === $key || empty( $f['label'] ) ) {
			continue;
		}
		$clean[ $key ] = array_merge(
			array(
				'type'        => 'text',
				'options'     => array(),
				'required'    => false,
				'about'       => false,
				'prefill'     => '',
				'placeholder' => '',
			),
			$f
		);
	}
	return $clean;
}

/**
 * Render a form to a string, using the theme's override if it has one
 * (<theme>/enquiries/form.php), otherwise the plugin's template.
 *
 * @param array $args See grow_enquiry_form().
 * @return string
 */
function gre_render_form( $args = array() ) {
	$args            = array_merge( gre_form_defaults(), (array) $args );
	$args['form_id'] = sanitize_key( $args['form_id'] ) ? sanitize_key( $args['form_id'] ) : 'general';
	$args['about']   = (int) $args['about'];
	$args['button']  = '' !== $args['button'] ? $args['button'] : (string) gre_setting( 'button' );

	gre_enqueue_assets();

	$feedback = gre_feedback( $args['form_id'] );

	$about_label = '';
	if ( $args['about'] && get_post( $args['about'] ) ) {
		$about_label = (string) apply_filters( 'grow_enquiry_about_label', get_the_title( $args['about'] ), $args['about'] );
	}

	$gre = array(
		'args'        => $args,
		'id'          => 'enquiry-' . $args['form_id'],
		'feedback'    => $feedback,
		'old'         => $feedback ? $feedback['old'] : array(),
		'about_label' => $about_label,
		'fields'      => gre_extra_fields( $args['form_id'] ),
		'phone'       => (bool) gre_setting( 'phone' ),
		'settings'    => gre_settings(),
	);

	$template = locate_template( 'enquiries/form.php' );
	if ( ! $template ) {
		$template = GRE_DIR . 'templates/form.php';
	}

	ob_start();
	include $template;
	return (string) ob_get_clean();
}

/**
 * The hidden fields every form needs. A theme override must call this,
 * so the spam checks can't be broken by accident.
 *
 * @param array $gre The template data.
 */
function grow_enquiry_hidden_fields( $gre ) {
	?>
	<input type="hidden" name="action" value="grow_enquiry">
	<input type="hidden" name="gre_form" value="<?php echo esc_attr( $gre['args']['form_id'] ); ?>">
	<input type="hidden" name="gre_about" value="<?php echo esc_attr( (string) $gre['args']['about'] ); ?>">
	<input type="hidden" name="gre_js" value="" data-gre-js>
	<input type="hidden" name="gre_elapsed" value="" data-gre-elapsed>
	<div class="gre-hp" aria-hidden="true">
		<label for="<?php echo esc_attr( $gre['id'] ); ?>-website">Leave this empty</label>
		<input type="text" id="<?php echo esc_attr( $gre['id'] ); ?>-website" name="gre_website" value="" tabindex="-1" autocomplete="off">
	</div>
	<?php
	if ( gre_turnstile_on() ) {
		printf(
			'<div class="cf-turnstile gre-turnstile" data-sitekey="%s" data-appearance="interaction-only" data-size="flexible"></div>',
			esc_attr( (string) gre_setting( 'turnstile_site' ) )
		);
	}
}

/**
 * Old value for a field after a validation error.
 *
 * @param array  $gre Template data.
 * @param string $key Field.
 * @return string
 */
function gre_old( $gre, $key ) {
	return isset( $gre['old'][ $key ] ) ? (string) $gre['old'][ $key ] : '';
}

/**
 * Stylesheet and script. Called by the form itself; a theme can call it
 * earlier (in wp_enqueue_scripts) so the stylesheet lands in <head>.
 */
function gre_enqueue_assets() {
	if ( gre_setting( 'stylesheet' ) ) {
		wp_enqueue_style( 'grow-enquiries', GRE_URL . 'assets/form.css', array(), GRE_VERSION );
	}
	wp_enqueue_script(
		'grow-enquiries',
		GRE_URL . 'assets/form.js',
		array(),
		GRE_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script( 'grow-enquiries', 'GRE', array( 'token' => GRE_JS_TOKEN ) );
	if ( gre_turnstile_on() ) {
		wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'in_footer' => true, 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}
}

/**
 * Enqueue early when the page content has the shortcode.
 */
function gre_maybe_enqueue_for_shortcode() {
	if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'enquiry_form' ) ) {
		gre_enqueue_assets();
	}
}
add_action( 'wp_enqueue_scripts', 'gre_maybe_enqueue_for_shortcode' );

/* =========================================================
   After the redirect: the thank-you or the error
   ========================================================= */

/**
 * Feedback for this request, if it belongs to this form. Status and the
 * visitor's typing sit server side for five minutes, keyed by a random
 * token in the URL. Nothing personal ever goes in the URL.
 *
 * @param string $form_id Form.
 * @return array|null { status, message, old }
 */
function gre_feedback( $form_id ) {
	if ( ! array_key_exists( 'gre_feedback', $GLOBALS ) ) {
		$GLOBALS['gre_feedback'] = null;
		$token                   = isset( $_GET['enq'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['enq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $token ) {
			$data = get_transient( 'gre_fb_' . $token );
			if ( is_array( $data ) ) {
				delete_transient( 'gre_fb_' . $token );
				$GLOBALS['gre_feedback'] = $data;
			}
		}
	}
	$fb = $GLOBALS['gre_feedback'];
	return ( $fb && isset( $fb['form_id'] ) && $fb['form_id'] === $form_id ) ? $fb : null;
}

/**
 * Stash feedback and go back to the form.
 *
 * @param string $back    URL.
 * @param string $form_id Form.
 * @param string $status  success | error.
 * @param string $message Shown to the visitor (errors only).
 * @param array  $old     Their typing, kept on an error.
 */
function gre_redirect( $back, $form_id, $status, $message = '', $old = array() ) {
	$token = wp_generate_password( 20, false );
	set_transient(
		'gre_fb_' . $token,
		array(
			'form_id' => $form_id,
			'status'  => $status,
			'message' => $message,
			'old'     => $old,
		),
		5 * MINUTE_IN_SECONDS
	);
	$back = remove_query_arg( 'enq', $back );
	$back = preg_replace( '/#.*$/', '', $back );
	wp_safe_redirect( add_query_arg( 'enq', $token, $back ) . '#enquiry-' . $form_id );
	exit;
}

/* =========================================================
   The handler
   ========================================================= */

/**
 * Handle a submission (admin-post.php, action grow_enquiry).
 */
function gre_handle() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form, see the file header.
	$raw     = wp_unslash( $_POST );
	$form_id = isset( $raw['gre_form'] ) ? sanitize_key( $raw['gre_form'] ) : 'general';
	$form_id = $form_id ? $form_id : 'general';

	$back = wp_get_referer();
	if ( ! $back || wp_parse_url( $back, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$back = home_url( '/' );
	}

	// Rate limit first: a visible error, because a person can wait.
	if ( ! gre_rate_ok() ) {
		gre_redirect( $back, $form_id, 'error', __( 'Too many messages in a short time. Please try again a little later.', 'grow-enquiries' ) );
	}

	$sub = array(
		'form_id'     => $form_id,
		'name'        => isset( $raw['gre_name'] ) ? mb_substr( sanitize_text_field( (string) $raw['gre_name'] ), 0, 100 ) : '',
		'email'       => isset( $raw['gre_email'] ) ? sanitize_email( (string) $raw['gre_email'] ) : '',
		'phone'       => isset( $raw['gre_phone'] ) ? mb_substr( sanitize_text_field( (string) $raw['gre_phone'] ), 0, 40 ) : '',
		'message'     => isset( $raw['gre_message'] ) ? mb_substr( sanitize_textarea_field( (string) $raw['gre_message'] ), 0, 5000 ) : '',
		'about'       => 0,
		'about_label' => '',
		'extras'      => array(),
		'extras_text' => '',
		'page'        => esc_url_raw( $back ),
	);

	// What it's about: a post, if one was passed and is public.
	$about = isset( $raw['gre_about'] ) ? absint( $raw['gre_about'] ) : 0;
	if ( $about && 'publish' === get_post_status( $about ) ) {
		$sub['about']       = $about;
		$sub['about_label'] = (string) apply_filters( 'grow_enquiry_about_label', get_the_title( $about ), $about );
	}

	// Extra fields.
	$errors = array();
	$old    = array();
	foreach ( gre_extra_fields( $form_id ) as $key => $f ) {
		$value = isset( $raw['gre_extra'][ $key ] ) ? sanitize_text_field( (string) $raw['gre_extra'][ $key ] ) : '';
		if ( 'select' === $f['type'] && '' !== $value && ! array_key_exists( $value, (array) $f['options'] ) ) {
			$value = '';
		}
		if ( $f['required'] && '' === $value ) {
			/* translators: %s: field label */
			$errors[] = sprintf( __( 'Please fill in "%s".', 'grow-enquiries' ), $f['label'] );
		}
		$old[ 'extra_' . $key ] = $value;
		$shown                  = ( 'select' === $f['type'] && '' !== $value ) ? (string) $f['options'][ $value ] : $value;
		if ( $f['about'] && '' !== $value && '' === $sub['about_label'] ) {
			$sub['about_label'] = $shown;
		} elseif ( '' !== $shown ) {
			$sub['extras'][ $f['label'] ] = $shown;
		}
	}
	foreach ( $sub['extras'] as $label => $value ) {
		$sub['extras_text'] .= $label . ': ' . $value . "\n";
	}

	if ( '' === trim( $sub['name'] ) ) {
		$errors[] = __( 'Please tell us your name.', 'grow-enquiries' );
	}
	if ( ! is_email( $sub['email'] ) ) {
		$errors[] = __( 'Please enter a valid email address.', 'grow-enquiries' );
	}
	if ( '' === trim( $sub['message'] ) ) {
		$errors[] = __( 'Please add a message.', 'grow-enquiries' );
	}

	if ( $errors ) {
		gre_redirect(
			$back,
			$form_id,
			'error',
			implode( ' ', $errors ),
			array_merge(
				$old,
				array(
					'name'    => $sub['name'],
					'email'   => isset( $raw['gre_email'] ) ? sanitize_text_field( (string) $raw['gre_email'] ) : '',
					'phone'   => $sub['phone'],
					'message' => $sub['message'],
				)
			)
		);
	}

	$reasons = gre_spam_reasons( $sub, $raw );
	// phpcs:enable

	$id = gre_log( $sub, $reasons );

	// Shadow-accept: held enquiries aren't emailed, but the sender sees the
	// same thank-you as everyone else.
	if ( ! $reasons && $id ) {
		gre_deliver( $id );
	}

	/**
	 * After an enquiry is logged (and emailed, if clean).
	 *
	 * @param int      $id      Enquiry ID.
	 * @param string[] $reasons Spam reasons; empty if clean.
	 */
	do_action( 'grow_enquiry_received', $id, $reasons );

	// The visitor sees the thank-you even if the email failed: the message
	// is safe in the log and the hourly job retries it. The recipient is
	// never printed.
	gre_redirect( $back, $form_id, 'success' );
}
add_action( 'admin_post_nopriv_grow_enquiry', 'gre_handle' );
add_action( 'admin_post_grow_enquiry', 'gre_handle' );
