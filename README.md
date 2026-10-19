# Grow Enquiries

Enquiry forms for Grow Creative's WordPress sites. One plugin, every site, so a fix goes out once.

- A private **Enquiries** log in wp-admin: Inbox and Spam views, unread counts, a read-only view with Reply, Release and send, Mark as spam, Block sender.
- **Cache-safe spam protection**: honeypot, JavaScript token and time on page, content scoring with per-site extra and ignored terms and sector packs, blocked senders, a per-visitor rate limit, optional Cloudflare Turnstile.
- **Shadow-accept**: anything caught is logged, not emailed, and the sender still sees the thank-you.
- **The recipient's address is never printed** on any page, including on errors.
- Email to the recipients with Reply-To set to the visitor; HTML or plain text. A failed email is retried hourly, up to 3 times, then flagged for a reply by hand.
- A **digest** of held spam (monthly, weekly or off), which also lists any enquiry whose email failed, so nothing is deleted unseen.
- Daily clean-up: held spam after 60 days, genuine enquiries after 24 months (both settings).
- **CSV export** of the view on screen, with a date range. WordPress's **Export and Erase Personal Data** tools find enquiries by email address, and the privacy policy guide gets matching wording.
- A small **Dashboard widget**: unread, held as spam this month, not emailed.
- **Updates from GitHub releases**, like any other plugin.

The plan this implements: `sites/enquiry-module-plan.md` (19th October, 2026). v0.1 was phase 1 of that plan; v0.2 is phase 2.

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
- `grow_enquiry_flagged` action ( $id ): an email has failed every retry
- `grow_enquiry_update_checker` ( $on ): from a must-use plugin only, as it runs when the plugin loads

Turnstile keys can be set as `GRE_TURNSTILE_SITE_KEY` and `GRE_TURNSTILE_SECRET_KEY` in wp-config.php instead of the database.

## WP-CLI

```
wp grow-enquiries list [--view=inbox|spam|failed] [--format=table|csv|json|ids]
wp grow-enquiries release <id>...     # also spam, read, resend, bin
wp grow-enquiries retry               # the hourly retry, now
wp grow-enquiries digest [--preview] [--if-due]
```

`gre_do_action( $do, $id )` works from scripts too (`wp eval`), with no need to load the admin files.

## Releases and updates

Sites update from GitHub releases through the bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT, in `lib/`). The repo is public, so no token is needed anywhere.

To release:

1. Bump the version in the plugin header and `GRE_VERSION` (they must match), and add a CHANGELOG.md entry headed `## x.y.z (date)`.
2. Commit, then tag and push: `git tag vX.Y.Z && git push origin main vX.Y.Z`.
3. The Release workflow (`.github/workflows/release.yml`) checks the versions match the tag, builds `grow-enquiries.zip` with `git archive` (`.gitattributes` leaves out `.github` and the git files), and attaches it to a release whose notes are that CHANGELOG entry.
4. Sites show the update in Dashboard, Updates within 12 hours (or straight away after "Check for updates"). Update Grow Creative's site first, check it, then the rest; `wp plugin update grow-enquiries` over SSH works too.

The update checker is off in a git checkout (a folder with `.git`), so a development copy is never overwritten, and off wherever `GRE_DISABLE_UPDATES` is true in wp-config.php. A must-use plugin can force it either way with the `grow_enquiry_update_checker` filter.

## Not in v0.2 yet

From phase 3 of the plan: the migration tool (`wp grow-enquiries migrate`) and legacy form names for Adrian Paye and Seven Dunes.
