=== DMBC Tools ===
Contributors: sbetts
Donate link: https://github.com/zagrev/dmbc-tools
Tags: dmbc, tools
Requires at least: 6.0
Requires PHP: 8.0
Tested up to: 6.8
Stable tag: 1.1.25
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

== Changelog ==

= 0.1.0 =
* Initial class-based plugin scaffold.
