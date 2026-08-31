<?php

use DmbcTools\Plugin;

/**
 * Tests for the DMBC Tools plugin metadata and Plugin class behavior.
 */
final class PluginTest extends DmbcUnitTestBase {

	/**
	 * Method test_plugin_file_contains_expected_metadata() verifies that the plugin file contains the expected metadata.
	 *
	 * @covers \dmbc_tools\Plugin
	 */
	public function test_plugin_file_contains_expected_metadata(): void {
		$contents = \file_get_contents( dirname( __DIR__ ) . '/dmbc-tools.php' );

		$this->assertIsString( $contents );
		$this->assertStringContainsString( 'Plugin Name: DMBC Tools', $contents );
		$this->assertStringContainsString( 'Text Domain: dmbc-tools', $contents );
	}

	/**
	 * Method instance() always returns the same singleton object.
	 *
	 * @covers \DmbcTools\Plugin::instance
	 */
	public function test_instance_returns_the_same_singleton(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	/**
	 * Method register_songlist_type() registers the custom post type when it isn't already registered.
	 *
	 * @covers \DmbcTools\Plugin::register_songlist_type
	 */
	public function test_register_songlist_type_registers_when_not_already_registered(): void {
		$plugin = Plugin::instance();
		$this->set_existing_post_types( array() );

		$plugin->register_songlist_type();

		$registered = $GLOBALS['dmbc_test_state']['registered_post_types'];
		$this->assertArrayHasKey( 'dmbc-songlist', $registered );
		$this->assertSame( 'Song Lists', $registered['dmbc-songlist']['labels']['name'] );
		$this->assertSame( 'dashicons-playlist-audio', $registered['dmbc-songlist']['menu_icon'] );
		$this->assertSame( array( 'slug' => 'songlists' ), $registered['dmbc-songlist']['rewrite'] );
	}

	/**
	 * Method register_songlist_type() does not re-register the post type when it already exists.
	 *
	 * @covers \DmbcTools\Plugin::register_songlist_type
	 */
	public function test_register_songlist_type_skips_when_already_registered(): void {
		$plugin = Plugin::instance();
		$this->set_existing_post_types( array( 'dmbc-songlist' ) );

		$plugin->register_songlist_type();

		$this->assertArrayNotHasKey( 'dmbc-songlist', $GLOBALS['dmbc_test_state']['registered_post_types'] );
	}

	/**
	 * Method register_options() adds the plugin's version option with its default value.
	 *
	 * @covers \DmbcTools\Plugin::register_options
	 */
	public function test_register_options_adds_version_option(): void {
		$plugin = Plugin::instance();

		$plugin->register_options();

		$this->assertSame( '0.1.0', get_option( 'dmbc_tools_version' ) );
	}

	/**
	 * Method uninstall() removes the plugin's version option.
	 *
	 * @covers \DmbcTools\Plugin::uninstall
	 */
	public function test_uninstall_removes_version_option(): void {
		$this->set_option( 'dmbc_tools_version', '0.1.0' );

		Plugin::uninstall();

		$this->assertFalse( get_option( 'dmbc_tools_version', false ) );
	}

	/**
	 * Method deactivate() flushes the rewrite rules.
	 *
	 * @covers \DmbcTools\Plugin::deactivate
	 */
	public function test_deactivate_flushes_rewrite_rules(): void {
		$plugin = Plugin::instance();

		$plugin->deactivate();

		$this->assertSame( 1, $GLOBALS['dmbc_test_state']['flush_rewrite_rules_calls'] );
	}

	/**
	 * Method admin_success() registers an admin_notices callback that surfaces the given message.
	 *
	 * @covers \DmbcTools\Plugin::admin_success
	 */
	public function test_admin_success_registers_notice_containing_message(): void {
		$plugin = Plugin::instance();

		$plugin->admin_success( 'Saved successfully' );

		$callbacks = $this->get_registered_actions( 'admin_notices' );
		$this->assertCount( 1, $callbacks );
		$this->assertStringContainsString( 'Saved successfully', call_user_func( $callbacks[0] ) );
	}

	/**
	 * Method add_admin_menu() registers the top-level menu and its submenu pages with the expected capabilities.
	 *
	 * @covers \DmbcTools\Plugin::add_admin_menu
	 */
	public function test_add_admin_menu_registers_menu_and_submenu_pages(): void {
		$plugin = Plugin::instance();

		$plugin->add_admin_menu();

		$menu_pages = $GLOBALS['dmbc_test_state']['menu_pages'];
		$this->assertArrayHasKey( 'dmbc-songlists-menu', $menu_pages );
		$this->assertSame( Plugin::CAP_VIEW_SONGLISTS, $menu_pages['dmbc-songlists-menu']['capability'] );

		$submenu_pages = $GLOBALS['dmbc_test_state']['submenu_pages'];
		$this->assertArrayHasKey( 'dmbc-songlist-edit', $submenu_pages );
		$this->assertSame( Plugin::CAP_EDIT_SONGLIST, $submenu_pages['dmbc-songlist-edit']['capability'] );

		$this->assertArrayHasKey( 'dmbc-tools-settings', $submenu_pages );
		$this->assertSame( 'options-general.php', $submenu_pages['dmbc-tools-settings']['parent_slug'] );
		$this->assertSame( 'manage_options', $submenu_pages['dmbc-tools-settings']['capability'] );
	}

	/**
	 * Method save_songlist_meta() does nothing when the meta nonce is missing from the request.
	 *
	 * @covers \DmbcTools\Plugin::save_songlist_meta
	 */
	public function test_save_songlist_meta_skips_when_nonce_missing(): void {
		$plugin = Plugin::instance();
		$_POST  = array( 'dmbc_notes' => 'should not be saved' );

		$plugin->save_songlist_meta( 101 );

		$this->assertNull( $this->get_stored_post_meta( 101, '_dmbc_notes' ) );
	}

	/**
	 * Method save_songlist_meta() does nothing when the meta nonce fails verification.
	 *
	 * @covers \DmbcTools\Plugin::save_songlist_meta
	 */
	public function test_save_songlist_meta_skips_when_nonce_invalid(): void {
		$plugin = Plugin::instance();
		$this->set_wp_verify_nonce_result( false );
		$_POST = array(
			'dmbc_songlist_meta_nonce' => 'some-nonce',
			'dmbc_notes'               => 'should not be saved',
		);

		$plugin->save_songlist_meta( 102 );

		$this->assertNull( $this->get_stored_post_meta( 102, '_dmbc_notes' ) );
	}

	/**
	 * Method save_songlist_meta() does nothing when the current user lacks edit permission.
	 *
	 * @covers \DmbcTools\Plugin::save_songlist_meta
	 */
	public function test_save_songlist_meta_skips_when_user_cannot_edit(): void {
		$plugin = Plugin::instance();
		$this->set_current_user_can( false );
		$_POST = array(
			'dmbc_songlist_meta_nonce' => 'some-nonce',
			'dmbc_notes'               => 'should not be saved',
		);

		$plugin->save_songlist_meta( 103 );

		$this->assertNull( $this->get_stored_post_meta( 103, '_dmbc_notes' ) );
	}

	/**
	 * Method save_songlist_meta() sanitizes and stores only the fields present in the request.
	 *
	 * @covers \DmbcTools\Plugin::save_songlist_meta
	 */
	public function test_save_songlist_meta_saves_only_submitted_fields(): void {
		$plugin = Plugin::instance();
		$_POST  = array(
			'dmbc_songlist_meta_nonce' => 'some-nonce',
			'dmbc_performance_date'    => '2024-01-01',
			'dmbc_notes'               => 'some notes',
		);

		$plugin->save_songlist_meta( 104 );

		$this->assertSame( '2024-01-01', $this->get_stored_post_meta( 104, '_dmbc_performance_date' ) );
		$this->assertSame( 'some notes', $this->get_stored_post_meta( 104, '_dmbc_notes' ) );
		$this->assertNull( $this->get_stored_post_meta( 104, '_dmbc_songs' ) );
	}

	/**
	 * Method render_songlist_meta_box() outputs the currently stored meta values.
	 *
	 * @covers \DmbcTools\Plugin::render_songlist_meta_box
	 */
	public function test_render_songlist_meta_box_outputs_stored_values(): void {
		$plugin = Plugin::instance();
		$this->set_post_meta( 105, '_dmbc_performance_date', '2024-05-01' );
		$this->set_post_meta( 105, '_dmbc_songs', "Song A\nSong B" );
		$this->set_post_meta( 105, '_dmbc_notes', 'Bring extra chairs' );

		ob_start();
		$plugin->render_songlist_meta_box( new \WP_Post( 105 ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( '2024-05-01', $html );
		$this->assertStringContainsString( 'Song A', $html );
		$this->assertStringContainsString( 'Bring extra chairs', $html );
	}
}
