<?php
/**
 * Mail server admin page.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Docker Mailserver management page (dashboard, accounts and aliases).
 */
class MailserverView {
	public const string MENU_SLUG = 'dmbc-mailserver';

	/**
	 * Render the mail server page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! \current_user_can( MailserverProxy::CAPABILITY ) ) {
			\wp_die( \esc_html__( 'You do not have permission to manage the mail server.', 'dmbc-tools' ), '', array( 'response' => 403 ) );
		}

		$assets_url = \plugin_dir_url( __FILE__ ) . 'templates/admin/';
		\wp_enqueue_style( 'dmbc-mailserver', $assets_url . 'mailserver.css', array(), Plugin::VERSION );
		\wp_enqueue_script( 'dmbc-mailserver', $assets_url . 'mailserver.js', array( 'wp-api-fetch' ), Plugin::VERSION, true );
		\wp_localize_script(
			'dmbc-mailserver',
			'dmbcMailserver',
			array( 'path' => '/' . MailserverProxy::REST_NAMESPACE . MailserverProxy::REST_BASE )
		);

		$api_url      = MailserverProxy::get_api_url();
		$settings_url = \admin_url( 'options-general.php?page=dmbc-tools-settings' );
		require __DIR__ . '/templates/admin/mailserver-page.php';
	}
}
