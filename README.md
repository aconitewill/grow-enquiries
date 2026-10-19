# Grow Enquiries

Enquiry forms for Grow Creative's WordPress sites. One plugin, every site, so a fix goes out once.

- A private **Enquiries** log in wp-admin: Inbox and Spam views, unread counts, a read-only view with Reply, Release and send, Mark as spam, Block sender.
- **Cache-safe spam protection**: honeypot, JavaScript token and time on page, content scoring with per-site extra and ignored terms and sector packs, blocked senders, a per-visitor rate limit, optional Cloudflare Turnstile.
- **Shadow-accept**: anything caught is logged, not emailed, and the sender still sees the thank-you.
- **The recipient's address is never printed** on any page, including on errors.
- Email to the recipients with Reply-To set to the visitor; HTML or plain text.
- Daily clean-up: held spam after 60 days, genuine enquiries after 24 months (both settings).

The plan this implements: `sites/enquiry-module-plan.md` (19th October, 2026). This is v0.1: phase 1 of that plan.

## Putting a form on a site

Template tag, in a theme:

```php
<?php if ( function_exists( 'grow_enquiry_form' ) ) { grow_enquiry_form( array( 'form_id' => 'contact' ) ); } ?>
```

Shortcode, in page content:

```
[enquiry_form form_id="contact"]
```

Arguments: `form_id` (when there are several on a page), `about` (a post ID; locks an "Enquiring about" field and names the subject), `heading`, `intro`, `button`.

### Extra fields

```php
add_filter( 'grow_enquiry_fields', function ( $fields, $form_id ) {
	if ( 'contact' === $form_id ) {
		$fields['topic'] = array(
			'label'   => "What's it about?",
			'type'    => 'select',            // text | select | date
			'options' => array( 'hosting' => 'Hosting', 'other' => 'Something else' ),
			'about'   => true,                 // the choice names the enquiry
			'prefill' => 'topic',              // ?topic=hosting pre-selects it, in the browser
		);
	}
	return $fields;
}, 10, 2 );
```

### Styling

1. The plugin stylesheet is all `:where()`, so any theme rule wins. Set `--gre-accent`, `--gre-ink`, `--gre-muted`, `--gre-bg`, `--gre-border`, `--gre-radius`, `--gre-font`, `--gre-label-font` on `.gre`.
2. Or switch the stylesheet off (Settings, Form) and style the stable classes (`.gre`, `.gre__field`, `.gre__button`, `.gre__notice--success` and so on).
3. Or copy `templates/form.php` to `<theme>/enquiries/form.php` and change the markup. Keep the field names and call `grow_enquiry_hidden_fields( $gre )` inside the form.

## Filters and actions

- `grow_enquiry_fields` ( $fields, $form_id )
- `grow_enquiry_about_label` ( $label, $post_id )
- `grow_enquiry_spam_terms` ( $terms ): label => array( pattern, weight )
- `grow_enquiry_settings` ( $settings )
- `grow_enquiry_roles` ( $roles ): who gets `manage_enquiries` (Administrator and Editor)
- `grow_enquiry_received` action ( $id, $reasons )

Turnstile keys can be set as `GRE_TURNSTILE_SITE_KEY` and `GRE_TURNSTILE_SECRET_KEY` in wp-config.php instead of the database.

## Not in v0.1 yet

From the plan's v0.2: monthly digest of held spam, CSV export, WordPress's personal data export and erase tools, retry for failed emails, dashboard widget, update checker and GitHub release workflow. From phase 3: the migration tool and legacy form names for Adrian Paye and Seven Dunes.
