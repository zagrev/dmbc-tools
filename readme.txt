=== DMBC Tools ===
Contributors: sbetts
Donate link: https://github.com/zagrev/dmbc-tools
Tags: dmbc, tools
Requires at least: 6.0
Requires PHP: 8.0
Tested up to: 6.8
Stable tag: 1.2.1
License: CC BY-NC-ND
License URI: https://creativecommons.org/licenses/by-nc-nd/4.0/legalcode.txt

Shared tools for the Dayton Metro Barbershop Chorus WordPress site.

== Description ==

DMBC Tools is a home for small, reusable integrations and utilities used by the chorus website.

== PHP Logging ==

The plugin includes a standalone application logger at src/logger.php. It is intended for general application-level logging, not authentication-specific logging.

Use the plugin singleton to access the logger from any plugin class:

<pre><code>use DmbcTools\Plugin;

$logger = Plugin::instance()->logger();
$logger->info( 'Member update digest sent successfully.' );
$logger->warn( 'Song list directory was not writable.', array( 'path' => $directory ) );
$logger->error( 'Unable to process transaction request.', array( 'request_id' => $request_id ) );
</code></pre>

The logger supports the standard application levels:

* DEBUG
* INFO
* WARN
* ERROR

Each log call can include structured context values. The message may include placeholders such as {path} or {request_id}, and the context is serialized for the WordPress/PHP logging pipeline:

<pre><code>$logger->info(
    'Processing request for {request_id}.',
    array( 'request_id' => $request_id )
);
</code></pre>

When WooCommerce is active, log entries are forwarded through wc_get_logger(). Otherwise they fall back to PHP error_log() so they still appear in normal WordPress/PHP logs.

== Rehearsal song list forms ==

Song list forms and edit/delete links include WordPress nonces. Create, update, and delete submissions verify the corresponding nonce before changing data. Invalid or expired submission nonces return an error; reload the page before trying again.

== Permalink rules ==

Activation registers the song list and member update post types, then refreshes WordPress rewrite rules so their archive and individual post URLs work immediately. Rewrite rules are not flushed on normal requests. For an already-active installation, deactivate and reactivate the plugin or save Settings > Permalinks to refresh the rules.

== IDE development ==

Run composer install with development dependencies to install php-stubs/wordpress-stubs. The package supplies WordPress function, class, and constant declarations for IDE completion and static analysis, including template functions such as language_attributes() and wp_head(). Let the PHP language server index vendor/php-stubs/wordpress-stubs; reload the VS Code window if stale unknown-function warnings remain. These declarations must not be included or required at runtime.

== Donations ==

Under Settings > DMBC Tools > DMBC Tools notifications, administrators can select separate published posts for the ticket and donation confirmation templates. The selected post's title supplies the subject and its content supplies the email body. Default retains lookup by "Ticket Purchase Confirmation Email" or "Donation Confirmation Email". A deleted or unpublished selected post is logged as unavailable and no confirmation is sent until the setting is corrected.

Approved Deluxe notifications whose SubmissionMethod contains "Donation" are recorded in the prefixed dmbc_donation table. Transaction IDs are unique; repeated notifications do not send another confirmation. Donor account creation and the donor role follow the existing ticket workflow.

Administrators can view Donations at wp-admin/admin.php?page=dmbc-donations. The page shows date, donor name, email, amount, transaction ID, donation count, and total amount. Records are private to users with manage_options; no public donation listing is added.

The Donations page defaults to the last 30 calendar days, including today according to the WordPress site date. Choose Last 90 days or Custom date range and apply the filter. Custom start and end dates are inclusive and filter the stored transaction date (tx_date), not the processing date. The displayed count and total cover only donations within the selected range.

Create a WordPress post titled "Donation Confirmation Email" to enable confirmation emails. The existing mailer supports {{CustomerName}}, {{PurchaseAmount}}, {{PurchaseDate}}, and {{ItemList}}{{ItemName}} / {{ItemQuantity}} / {{ItemPrice}}{{/ItemList}}. Notifications without SaleItems receive a single Donation line item. The configured plugin email recipient is copied. Missing templates and delivery failures are logged; donations remain recorded.

The donation table is created by the existing schema setup on init. The public Donate link and Deluxe payment form are unchanged.

== Changelog ==

= 1.2.0 =
* Add Mail Server admin page that manages Docker Mailserver accounts and aliases through a REST proxy to the Docker Mailserver GUI API.

= 0.1.0 =
* Initial class-based plugin scaffold.
