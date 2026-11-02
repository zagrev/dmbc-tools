# DMBC Tools

Shared tools for the **D**ayton **M**etro **B**arbershop **C**horus WordPress site.
DMBC Tools is a home for small, reusable integrations and utilities used by the chorus website.

## Features

### Membership roles

A chorus member may be a Member, a Director, or a Board Member.
Each role has different access to the features of the web site.

Additionally, the member profile has added information about the member.
This information includes the phone number, the vocal part the member prefers, and the Barbershop Harmony Society membership information.

### Content restriction filtering

The plugin provides for content filtering based on the user's role.
That is, pages, posts, events, etc. can be restricted to Members or Directors only.
If the user is not logged in, or is not a member of the allowed roles, will not be able to see the content that is restricted.

### Learning Tracks Library

This plugin provides a Song Library interface to allow the directors to manage the Learning Tracks owned by the chorus.
This tools allows the Directors to upload, move, rename, and delete Learning Tracks using a drag-and-drop interface.
All Members can view and download the Learning Tracks, either from the browsing interface or from the Song Lists.

### Song Lists

The Directors may create song lists by selecting Learning Tracks from the Song Library.
The order of the songs may be adjusted and notes can be added interleaved with the songs.
Song lists are lists of music from the learning track library providing members with easy access directly to the learning tracks without having to manually traverse the directory structure for the songs.
These song lists will be emailed to members when the lists are published.
Song lists can be downloaded as standard music playlists as well.

### Member News

Members can create Member News updates to provide pertinent information to all the chorus members.
When an update is published, each member will receive the news in an email.
This is useful to notify members of upcoming rehearsals, performances, and details about what to wear, when and where to meet, etc.

### Ticket Sales

The website integrates with the Deluxe Payment system to record and process ticket sales for performances.
When a user buys tickets using the Deluxe online form, a message is sent to the web server detailing the transaction information.
The website processes the transaction notification, recording the information into the database.
This includes creating an account for the purchaser as a Subscriber, including their email and address, so that we can contact them with news about performances and the annual show.
Then the website creates an email receipt for the buyer based on the selected template.

This template can easily be customized using the post editor built-in to Wordpress.
The template supports {{CustomerName}}, {{PurchaseAmount}}, {{PurchaseDate}}, and {{ItemList}}{{ItemName}} / {{ItemQuantity}} / {{ItemPrice}}{{/ItemList}}.

The ticket sales can be reviewed on the Ticket Sales page.

### Donations

The website integrates with the Deluxe Payment system to record and process online donations.
The website creates an email receipt for the donor using the selected email template.

This directors can customize this email using the post editor built-in to Wordpress.
The template supports {{CustomerName}}, {{PurchaseAmount}}, and {{PurchaseDate}}.

The donations can be reviewed on the Donations page.
The donations page defaults to showing the last 30 days of doantions, but can be customized to any date range.

### Mailing Lists

The plugin provides a mailing list capability.
Email sent to a single email address, like members@, directors@, or board@DaytonMetroBarbershopChorus.org will automatically be send to all email addresses in the mailing list group.
The advantage of this method is that members do not have to remember the email addresses of every other member to email them.
All emails hide the email addresses of members using BCC but replies are automatically sent to all recipients on the mailing list.

The mailing lists for directors and board members are manually maintained, but the members mailing list is automatically updated based on the Members role for the users.

### Supporting tools

DMBC Tools uses additional plugins for some of the functionality.
These include "Ultimate Member" for the roles and content restrictions base on those roles.

## Developing

There are additional capabilities in the plugin that are mostly used by developers and maintainers instead of hte chorus at large.

### PHP Logging

The plugin includes a standalone application logger at src/logger.php.
It is intended for general application-level logging, not authentication-specific logging.

Use the plugin singleton to access the logger from any plugin class:

```php
use DmbcTools\Plugin;

$logger = Plugin::instance()->logger();
$logger->info( 'Member update digest sent successfully.' );
$logger->warn( 'Song list directory was not writable.', array( 'path' => $directory ) );
$logger->error( 'Unable to process transaction request.', array( 'request_id' => $request_id ) );
```

The logger supports the standard application levels:

- DEBUG
- INFO
- WARN
- ERROR

Each log call can include structured context values.
The message may include placeholders such as {path} or {request_id}, and the context is serialized for the WordPress/PHP logging pipeline:

```php
$logger->info(
    'Processing request for {request_id}.',
    array( 'request_id' => $request_id )
);
```

When WooCommerce is active, log entries are forwarded through wc_get_logger().
Otherwise they fall back to PHP error_log() so they still appear in normal WordPress/PHP logs.

### Rehearsal song list forms

Song list forms and edit/delete links include WordPress nonces.
Create, update, and delete submissions verify the corresponding nonce before changing data.
Invalid or expired submission nonces return an error; reload the page before trying again.

### Permalink rules

Activation registers the song list and member update post types, then refreshes WordPress rewrite rules so their archive and individual post URLs work immediately.
Rewrite rules are not flushed on normal requests.
For an already-active installation, deactivate and reactivate the plugin or save Settings > Permalinks to refresh the rules.

### Mailster member group synchronization

The scheduled member group sync and the Run Member Groups Sync tool add WordPress users with the um_member role to the WP Mailster "members" group and remove WordPress-linked entries whose users no longer hold that role.
Cleanup also runs when no users hold the member role.
Only membership in the configured group is removed: WordPress accounts, Mailster contacts, other groups, and manually added non-WordPress contacts are left unchanged.
The existing group-name and member-role filters still apply.
Addition and removal counts, along with database write failures, are logged.

### Member Update layouts

Individual Member Updates and the Member Updates archive use the active WordPress theme's header and footer template parts, including Site Editor customizations.
The plugin supplies the update title, date, and content layout; header and footer parts are not tied to a particular theme.

### IDE development

Run composer install with development dependencies to install php-stubs/wordpress-stubs.
The package supplies WordPress function, class, and constant declarations for IDE completion and static analysis, including template functions such as language_attributes() and wp_head().
Let the PHP language server index vendor/php-stubs/wordpress-stubs; reload the VS Code window if stale unknown-function warnings remain.
These declarations must not be included or required at runtime.

### Donations

Under Settings > DMBC Tools > DMBC Tools notifications, administrators can select separate published posts for the ticket and donation confirmation templates.
The selected post's title supplies the subject and its content supplies the email body.
Default retains lookup by "Ticket Purchase Confirmation Email" or "Donation Confirmation Email".
A deleted or unpublished selected post is logged as unavailable and no confirmation is sent until the setting is corrected.

Approved Deluxe notifications whose SubmissionMethod contains "Donation" are recorded in the prefixed dmbc_donation table.
A donor account is created or retrieved when the donation includes an email address; donations without an email are still recorded without creating a user.
Transaction IDs are unique; repeated notifications do not send another confirmation.
Ticket purchases continue to create or retrieve a user and assign the donor role as before.

Administrators can view Donations at wp-admin/admin.php?page=dmbc-donations.
The page shows date, donor name, email, amount, transaction ID, donation count, and total amount.
Records are private to users with manage_options; no public donation listing is added.

The Donations page defaults to the last 30 calendar days, including today according to the WordPress site date. Choose Last 90 days or Custom date range and apply the filter.
Custom start and end dates are inclusive and filter the stored transaction date (tx_date), not the processing date.
The displayed count and total cover only donations within the selected range.

Create a WordPress post titled "Donation Confirmation Email" to enable confirmation emails.
Notifications without SaleItems receive a single Donation line item.
The configured plugin email recipient is copied. Missing templates and delivery failures are logged; donations remain recorded.
