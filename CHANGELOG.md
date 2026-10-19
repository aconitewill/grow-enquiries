# Changelog

## 0.2.0 (19th October, 2026)

Phase 2 of the enquiry module plan.

- **Digest of held spam**: an email listing what the spam filter held since the last one, and any genuine enquiry whose email failed, each linking to its enquiry. Monthly (the 1st) by default, or weekly (Mondays), or off; to the recipients or a separate address (Settings, Retention and digest). Only sent when there's something in it. "Send a preview of the digest" on the Tools tab, and `wp grow-enquiries digest`.
- **Failed emails retried** hourly, up to 3 times, then flagged: a red "Not emailed: reply by hand" badge, a notice on the Enquiries screens, and a "Not emailed" view (shown when there are any) with a bulk Resend email. The enquiry view shows the mail error and how many retries are left.
- **CSV export** of the view on screen (Inbox, Spam, Not emailed or Bin), with From and To dates that also filter the list. Cells that a spreadsheet would run as formulas are made safe.
- **Personal data tools**: Tools, Export Personal Data and Erase Personal Data find enquiries by email address. Suggested wording added to Settings, Privacy, Policy guide, following this site's retention settings (also shown on the Retention and digest tab).
- **Dashboard widget**: unread, held as spam this month, and not emailed.
- **Updates from GitHub releases**: bundles Plugin Update Checker 5.7 (MIT), pointed at aconitewill/grow-enquiries; sites see new versions in Dashboard, Updates. Off in a git checkout (so updating a dev copy can't wipe .git) or with `GRE_DISABLE_UPDATES`. A GitHub Action builds a clean zip on each `v*` tag and attaches it to the release.
- **Fix**: `gre_do_action()` and `gre_action_url()` now load everywhere, not just in wp-admin, so WP-CLI and scripts can release or mark enquiries. New `wp grow-enquiries` command: list, release, spam, read, resend, bin, retry, digest.

## 0.1.0 (19th October, 2026)

First version, built for growcreative.com from the enquiry module plan. Merges the Adrian Paye and Seven Dunes theme modules: private log with Inbox and Spam, shadow-accept, honeypot, JavaScript token and time check, content scoring (Seven Dunes' list, with labels so a site can ignore terms), sector packs, extra terms, blocked senders, rate limit, optional Turnstile, release and send, daily purge of spam and old enquiries. New: settings screen with tabs, read-only enquiry view, unread counts, test email, test-a-message, template overrides, extra fields, HTML or plain email.
