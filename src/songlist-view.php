<?php
declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * Class to render the standard song list pages
 */
class SongListView {
	/**
	 *
	 * render the list of song lists admin page
	 *
	 * @return void
	 */
	public static function dmbc_render_songlist_table_page(): void {
		\error_log( 'DMBC Plugin: dmbc_render_songlist_table_page method called.' );
		?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Rehearsal Song Lists', 'dmbc-tools' ); ?></h1>
		<p><?php esc_html_e( 'Here you can view and manage all rehearsal song lists.', 'dmbc-tools' ); ?></p>
	</div>
		<?php
	}

	/**
	 * Renders the admin page for editing a single song list.
	 *
	 * @return void
	 */
	public static function dmbc_render_songlist_edit_page(): void {
		\error_log( 'DMBC Plugin: dmbc_render_songlist_edit_page method called.' );
		?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Rehearsal Song Lists', 'dmbc-tools' ); ?></h1>
		<p><?php esc_html_e( 'Here you can view and manage all rehearsal song lists.', 'dmbc-tools' ); ?></p>
	</div>
		<?php
	}

	/**
	 * Renders the admin page for configuring the DMBC TOOLS plugin settings.
	 *
	 * @return void
	 */
	public static function dmbc_render_settings_page(): void {
		\error_log( 'DMBC Plugin: dmbc_render_settings_page method called.' );
		?>
	<div class="wrap">
		<h1><?php esc_html_e( 'DMBC TOOLS Settings', 'dmbc-tools' ); ?></h1>
		<p><?php esc_html_e( 'Here you can configure the settings for the DMBC TOOLS plugin.', 'dmbc-tools' ); ?></p>
	</div>
		<?php
	}
} // End of SongListView class
