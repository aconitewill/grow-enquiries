<?php
/**
 * The enquiry form.
 *
 * Override it by copying this file to <theme>/enquiries/form.php. Keep the
 * field names (gre_name, gre_email, gre_phone, gre_message, gre_extra[key])
 * and call grow_enquiry_hidden_fields( $gre ) inside the <form>.
 *
 * Available: $gre = array(
 *     args        => form_id, about, heading, intro, button
 *     id          => the wrapper's id, for the redirect anchor
 *     feedback    => null, or status / message after a submit
 *     old         => the visitor's typing after an error
 *     about_label => the locked "Enquiring about" text, if any
 *     fields      => extra fields from the grow_enquiry_fields filter
 *     phone       => whether to show the phone field
 *     settings    => all settings
 * )
 *
 * @package GrowEnquiries
 */

defined( 'ABSPATH' ) || exit;

$gre_fb = $gre['feedback'];
$gre_s  = $gre['settings'];
$gre_p  = $gre['id'];
?>
<div class="gre" id="<?php echo esc_attr( $gre['id'] ); ?>">

	<?php if ( $gre['args']['heading'] ) : ?>
		<h2 class="gre__heading"><?php echo esc_html( $gre['args']['heading'] ); ?></h2>
	<?php endif; ?>
	<?php if ( $gre['args']['intro'] ) : ?>
		<p class="gre__intro"><?php echo esc_html( $gre['args']['intro'] ); ?></p>
	<?php endif; ?>

	<?php if ( $gre_fb && 'success' === $gre_fb['status'] ) : ?>

		<div class="gre__notice gre__notice--success" role="status" tabindex="-1" data-gre-focus>
			<strong><?php echo esc_html( $gre_s['thanks_heading'] ); ?></strong>
			<?php if ( $gre_s['thanks_text'] ) : ?>
				<span><?php echo esc_html( $gre_s['thanks_text'] ); ?></span>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<?php if ( $gre_fb && 'error' === $gre_fb['status'] && $gre_fb['message'] ) : ?>
			<div class="gre__notice gre__notice--error" role="alert" tabindex="-1" data-gre-focus>
				<?php echo esc_html( $gre_fb['message'] ); ?>
			</div>
		<?php endif; ?>

		<form class="gre__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-gre-form>
			<?php grow_enquiry_hidden_fields( $gre ); ?>

			<?php if ( $gre['about_label'] ) : ?>
				<p class="gre__field gre__field--about">
					<label for="<?php echo esc_attr( $gre_p ); ?>-about"><?php esc_html_e( 'Enquiring about', 'grow-enquiries' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $gre_p ); ?>-about" value="<?php echo esc_attr( $gre['about_label'] ); ?>" readonly>
				</p>
			<?php endif; ?>

			<p class="gre__field">
				<label for="<?php echo esc_attr( $gre_p ); ?>-name"><?php esc_html_e( 'Your name', 'grow-enquiries' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $gre_p ); ?>-name" name="gre_name" required maxlength="100" autocomplete="name" value="<?php echo esc_attr( gre_old( $gre, 'name' ) ); ?>">
			</p>

			<p class="gre__field">
				<label for="<?php echo esc_attr( $gre_p ); ?>-email"><?php esc_html_e( 'Email address', 'grow-enquiries' ); ?></label>
				<input type="email" id="<?php echo esc_attr( $gre_p ); ?>-email" name="gre_email" required autocomplete="email" value="<?php echo esc_attr( gre_old( $gre, 'email' ) ); ?>">
			</p>

			<?php if ( $gre['phone'] ) : ?>
				<p class="gre__field">
					<label for="<?php echo esc_attr( $gre_p ); ?>-phone"><?php esc_html_e( 'Phone (optional)', 'grow-enquiries' ); ?></label>
					<input type="tel" id="<?php echo esc_attr( $gre_p ); ?>-phone" name="gre_phone" maxlength="40" autocomplete="tel" value="<?php echo esc_attr( gre_old( $gre, 'phone' ) ); ?>">
				</p>
			<?php endif; ?>

			<?php foreach ( $gre['fields'] as $gre_key => $gre_f ) : ?>
				<?php
				$gre_fid = $gre_p . '-' . $gre_key;
				$gre_val = gre_old( $gre, 'extra_' . $gre_key );
				?>
				<p class="gre__field gre__field--<?php echo esc_attr( $gre_key ); ?>">
					<label for="<?php echo esc_attr( $gre_fid ); ?>"><?php echo esc_html( $gre_f['label'] ); ?><?php echo $gre_f['required'] ? '' : ' <span class="gre__optional">' . esc_html__( '(optional)', 'grow-enquiries' ) . '</span>'; ?></label>
					<?php if ( 'select' === $gre_f['type'] ) : ?>
						<select id="<?php echo esc_attr( $gre_fid ); ?>" name="gre_extra[<?php echo esc_attr( $gre_key ); ?>]" <?php echo $gre_f['required'] ? 'required' : ''; ?> <?php echo $gre_f['prefill'] ? 'data-gre-prefill="' . esc_attr( $gre_f['prefill'] ) . '"' : ''; ?>>
							<option value=""><?php echo esc_html( $gre_f['placeholder'] ? $gre_f['placeholder'] : __( 'Choose one', 'grow-enquiries' ) ); ?></option>
							<?php foreach ( (array) $gre_f['options'] as $gre_ov => $gre_ol ) : ?>
								<option value="<?php echo esc_attr( $gre_ov ); ?>" <?php selected( $gre_val, (string) $gre_ov ); ?>><?php echo esc_html( $gre_ol ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="<?php echo 'date' === $gre_f['type'] ? 'date' : 'text'; ?>" id="<?php echo esc_attr( $gre_fid ); ?>" name="gre_extra[<?php echo esc_attr( $gre_key ); ?>]" value="<?php echo esc_attr( $gre_val ); ?>" placeholder="<?php echo esc_attr( $gre_f['placeholder'] ); ?>" <?php echo $gre_f['required'] ? 'required' : ''; ?> <?php echo $gre_f['prefill'] ? 'data-gre-prefill="' . esc_attr( $gre_f['prefill'] ) . '"' : ''; ?>>
					<?php endif; ?>
				</p>
			<?php endforeach; ?>

			<p class="gre__field">
				<label for="<?php echo esc_attr( $gre_p ); ?>-message"><?php esc_html_e( 'Message', 'grow-enquiries' ); ?></label>
				<textarea id="<?php echo esc_attr( $gre_p ); ?>-message" name="gre_message" rows="7" required maxlength="5000" data-gre-message><?php echo esc_textarea( gre_old( $gre, 'message' ) ); ?></textarea>
			</p>

			<?php if ( $gre_s['privacy_note'] ) : ?>
				<p class="gre__privacy"><?php echo esc_html( $gre_s['privacy_note'] ); ?></p>
			<?php endif; ?>

			<p class="gre__actions">
				<button type="submit" class="gre__button"><?php echo esc_html( $gre['args']['button'] ); ?></button>
			</p>
		</form>

	<?php endif; ?>
</div>
