<?php
/**
 * Mail server admin page template.
 *
 * @package DmbcTools
 *
 * @var string $api_url
 * @var string $settings_url
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<div class="wrap dmbc-mailserver">
	<h1><?php esc_html_e( 'Mail Server', 'dmbc-tools' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Requests are forwarded to the Docker Mailserver GUI API at', 'dmbc-tools' ); ?>
		<code><?php echo esc_html( $api_url ); ?></code>
		(<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'change', 'dmbc-tools' ); ?></a>).
	</p>

	<div id="dmbc-mailserver-notice" class="notice inline" hidden><p></p></div>

	<nav class="nav-tab-wrapper">
		<a href="#dashboard" class="nav-tab nav-tab-active" data-tab="dashboard"><?php esc_html_e( 'Dashboard', 'dmbc-tools' ); ?></a>
		<a href="#accounts" class="nav-tab" data-tab="accounts"><?php esc_html_e( 'Email Accounts', 'dmbc-tools' ); ?></a>
		<a href="#aliases" class="nav-tab" data-tab="aliases"><?php esc_html_e( 'Aliases', 'dmbc-tools' ); ?></a>
	</nav>

	<section class="dmbc-mailserver-tab" data-tab="dashboard">
		<div class="dmbc-mailserver-cards">
			<div class="dmbc-mailserver-card"><h3><?php esc_html_e( 'Server Status', 'dmbc-tools' ); ?></h3><p data-stat="status">&hellip;</p></div>
			<div class="dmbc-mailserver-card"><h3><?php esc_html_e( 'CPU Usage', 'dmbc-tools' ); ?></h3><p data-stat="cpu">&hellip;</p></div>
			<div class="dmbc-mailserver-card"><h3><?php esc_html_e( 'Memory Usage', 'dmbc-tools' ); ?></h3><p data-stat="memory">&hellip;</p></div>
			<div class="dmbc-mailserver-card"><h3><?php esc_html_e( 'Disk Usage', 'dmbc-tools' ); ?></h3><p data-stat="disk">&hellip;</p></div>
			<div class="dmbc-mailserver-card"><h3><?php esc_html_e( 'Email Accounts', 'dmbc-tools' ); ?></h3><p data-stat="accounts">&hellip;</p></div>
			<div class="dmbc-mailserver-card"><h3><?php esc_html_e( 'Aliases', 'dmbc-tools' ); ?></h3><p data-stat="aliases">&hellip;</p></div>
		</div>
		<p><button type="button" class="button" data-action="refresh"><?php esc_html_e( 'Refresh', 'dmbc-tools' ); ?></button></p>
	</section>

	<section class="dmbc-mailserver-tab" data-tab="accounts" hidden>
		<h2><?php esc_html_e( 'Add Email Account', 'dmbc-tools' ); ?></h2>
		<form id="dmbc-mailserver-account-form" class="dmbc-mailserver-form">
			<label for="dmbc-account-email"><?php esc_html_e( 'Email address', 'dmbc-tools' ); ?></label>
			<input type="email" id="dmbc-account-email" name="email" class="regular-text" required>
			<label for="dmbc-account-password"><?php esc_html_e( 'Password', 'dmbc-tools' ); ?></label>
			<input type="password" id="dmbc-account-password" name="password" class="regular-text" autocomplete="new-password" required>
			<label for="dmbc-account-password-confirm"><?php esc_html_e( 'Confirm password', 'dmbc-tools' ); ?></label>
			<input type="password" id="dmbc-account-password-confirm" name="password_confirm" class="regular-text" autocomplete="new-password" required>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Account', 'dmbc-tools' ); ?></button>
		</form>

		<h2><?php esc_html_e( 'Existing Accounts', 'dmbc-tools' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Email', 'dmbc-tools' ); ?></th>
					<th><?php esc_html_e( 'Storage Used', 'dmbc-tools' ); ?></th>
					<th><?php esc_html_e( 'Quota', 'dmbc-tools' ); ?></th>
					<th><?php esc_html_e( 'Usage', 'dmbc-tools' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'dmbc-tools' ); ?></th>
				</tr>
			</thead>
			<tbody id="dmbc-mailserver-accounts"><tr><td colspan="5"><?php esc_html_e( 'Loading…', 'dmbc-tools' ); ?></td></tr></tbody>
		</table>
	</section>

	<section class="dmbc-mailserver-tab" data-tab="aliases" hidden>
		<h2><?php esc_html_e( 'Add Alias', 'dmbc-tools' ); ?></h2>
		<form id="dmbc-mailserver-alias-form" class="dmbc-mailserver-form">
			<label for="dmbc-alias-source"><?php esc_html_e( 'Source address', 'dmbc-tools' ); ?></label>
			<input type="email" id="dmbc-alias-source" name="source" class="regular-text" required>
			<label for="dmbc-alias-destination"><?php esc_html_e( 'Destination address', 'dmbc-tools' ); ?></label>
			<input type="email" id="dmbc-alias-destination" name="destination" class="regular-text" list="dmbc-mailserver-account-list" required>
			<datalist id="dmbc-mailserver-account-list"></datalist>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Alias', 'dmbc-tools' ); ?></button>
		</form>

		<h2><?php esc_html_e( 'Existing Aliases', 'dmbc-tools' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Source', 'dmbc-tools' ); ?></th>
					<th><?php esc_html_e( 'Destination', 'dmbc-tools' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'dmbc-tools' ); ?></th>
				</tr>
			</thead>
			<tbody id="dmbc-mailserver-aliases"><tr><td colspan="3"><?php esc_html_e( 'Loading…', 'dmbc-tools' ); ?></td></tr></tbody>
		</table>
	</section>
</div>
