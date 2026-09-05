<?php

use DmbcTools\DmbcSettings;

/**
 * Tests for DmbcSettings covering its observable behavior: option
 * sanitization/retrieval, directory browsing, and settings registration.
 */
final class DmbcSettingsTest extends DmbcUnitTestBase {

	private function make_settings(): DmbcSettings {
		return new DmbcSettings();
	}

	// -- sanitize_song_library_directory -------------------------------------------------

	/**
	 * Backslashes are converted to forward slashes and outer whitespace/trailing slashes are trimmed.
	 *
	 * @covers \DmbcTools\DmbcSettings::sanitize_song_library_directory
	 */
	public function test_sanitize_song_library_directory_normalizes_separators_and_trims(): void {
		$settings = $this->make_settings();

		$this->assertSame( 'C:/foo/bar', $settings->sanitize_song_library_directory( '  C:\\foo\\bar\\  ' ) );
		$this->assertSame( 'relative/path', $settings->sanitize_song_library_directory( 'relative/path/' ) );
		$this->assertSame( '', $settings->sanitize_song_library_directory( '   ' ) );
	}

	// -- get_song_library_directory_option -----------------------------------------------

	/**
	 * Returns the default directory name when no option has been saved.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_library_directory_option
	 */
	public function test_get_song_library_directory_option_defaults_when_unset(): void {
		$settings = $this->make_settings();

		$this->assertSame( 'dmbc-song-library', $settings->get_song_library_directory_option() );
	}

	/**
	 * The stored option value is run through the same sanitization as direct input.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_library_directory_option
	 */
	public function test_get_song_library_directory_option_sanitizes_stored_value(): void {
		$settings = $this->make_settings();
		$this->set_option( 'song_library_directory', 'some\\dir\\path/' );

		$this->assertSame( 'some/dir/path', $settings->get_song_library_directory_option() );
	}

	// -- get_song_library_directory_path -------------------------------------------------

	/**
	 * A relative directory option is resolved to an absolute path under WP_CONTENT_DIR.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_library_directory_path
	 */
	public function test_get_song_library_directory_path_resolves_relative_option_under_wp_content(): void {
		$settings = $this->make_settings();
		$this->set_option( 'song_library_directory', 'custom-library' );

		$this->assertSame( WP_CONTENT_DIR . '/custom-library', $settings->get_song_library_directory_path() );
	}

	/**
	 * An already-absolute directory option is returned unchanged.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_library_directory_path
	 */
	public function test_get_song_library_directory_path_returns_absolute_option_as_is(): void {
		$settings   = $this->make_settings();
		$abs_folder = $this->create_temp_directory();
		$this->set_option( 'song_library_directory', $abs_folder );

		$this->assertSame( $abs_folder, $settings->get_song_library_directory_path() );
	}

	/**
	 * A blank directory option falls back to the default library folder name.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_library_directory_path
	 */
	public function test_get_song_library_directory_path_falls_back_to_default_when_option_empty(): void {
		$settings = $this->make_settings();
		$this->set_option( 'song_library_directory', '   ' );

		$this->assertSame( WP_CONTENT_DIR . '/dmbc-song-library', $settings->get_song_library_directory_path() );
	}

	// -- sanitize_song_library_exclusion_regexes -----------------------------------------

	/**
	 * A newline-delimited string is split into trimmed regexes with blank lines dropped.
	 *
	 * @covers \DmbcTools\DmbcSettings::sanitize_song_library_exclusion_regexes
	 */
	public function test_sanitize_song_library_exclusion_regexes_splits_trims_and_drops_empty_lines(): void {
		$settings = $this->make_settings();

		$result = $settings->sanitize_song_library_exclusion_regexes( "  ^foo  \n\nbar$\n  " );

		$this->assertSame( array( '^foo', 'bar$' ), $result );
	}

	/**
	 * Array input is accepted and entries that are not valid regexes are filtered out.
	 *
	 * @covers \DmbcTools\DmbcSettings::sanitize_song_library_exclusion_regexes
	 */
	public function test_sanitize_song_library_exclusion_regexes_filters_invalid_regex_and_accepts_arrays(): void {
		$settings = $this->make_settings();

		$result = $settings->sanitize_song_library_exclusion_regexes( array( 'valid.*', '(unterminated', ' ' ) );

		$this->assertSame( array( 'valid.*' ), $result );
	}

	// -- get_song_library_exclusion_regexes ----------------------------------------------

	/**
	 * The stored exclusion regexes option is sanitized before being returned.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_library_exclusion_regexes
	 */
	public function test_get_song_library_exclusion_regexes_sanitizes_stored_option(): void {
		$settings = $this->make_settings();
		$this->set_option( 'song_library_exclusion_regexes', array( ' foo ', '' ) );

		$this->assertSame( array( 'foo' ), $settings->get_song_library_exclusion_regexes() );
	}

	// -- sanitize_song_list_recipient_roles ----------------------------------------------

	/**
	 * Only roles that exist on the site are kept, and role slugs are normalized to lowercase.
	 *
	 * @covers \DmbcTools\DmbcSettings::sanitize_song_list_recipient_roles
	 */
	public function test_sanitize_song_list_recipient_roles_keeps_only_known_roles(): void {
		$settings = $this->make_settings();
		$this->set_wp_roles(
			array(
				'administrator' => array( 'name' => 'Administrator' ),
				'editor'        => array( 'name' => 'Editor' ),
			)
		);

		$result = $settings->sanitize_song_list_recipient_roles( array( 'administrator', 'EDITOR', 'bogus-role' ) );

		$this->assertSame( array( 'administrator', 'editor' ), $result );
	}

	// -- get_song_list_recipient_roles ---------------------------------------------------

	/**
	 * A non-array stored option results in an empty list of recipient roles.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_list_recipient_roles
	 */
	public function test_get_song_list_recipient_roles_returns_empty_array_for_non_array_option(): void {
		$settings = $this->make_settings();
		$this->set_option( 'song_list_recipient_roles', 'not-an-array' );

		$this->assertSame( array(), $settings->get_song_list_recipient_roles() );
	}

	/**
	 * The stored recipient roles array is sanitized against the known site roles.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_list_recipient_roles
	 */
	public function test_get_song_list_recipient_roles_sanitizes_stored_array(): void {
		$settings = $this->make_settings();
		$this->set_wp_roles( array( 'editor' => array( 'name' => 'Editor' ) ) );
		$this->set_option( 'song_list_recipient_roles', array( 'editor', 'bogus-role' ) );

		$this->assertSame( array( 'editor' ), $settings->get_song_list_recipient_roles() );
	}

	// -- sanitize_song_list_default_recipient --------------------------------------------

	/**
	 * A well-formed email address is returned unchanged.
	 *
	 * @covers \DmbcTools\DmbcSettings::sanitize_song_list_default_recipient
	 */
	public function test_sanitize_song_list_default_recipient_accepts_valid_email(): void {
		$settings = $this->make_settings();

		$this->assertSame( 'person@example.com', $settings->sanitize_song_list_default_recipient( 'person@example.com' ) );
	}

	/**
	 * An invalid email address is sanitized down to an empty string.
	 *
	 * @covers \DmbcTools\DmbcSettings::sanitize_song_list_default_recipient
	 */
	public function test_sanitize_song_list_default_recipient_rejects_invalid_email(): void {
		$settings = $this->make_settings();

		$this->assertSame( '', $settings->sanitize_song_list_default_recipient( 'not-an-email' ) );
	}

	// -- get_song_list_default_recipient --------------------------------------------------

	/**
	 * The stored default recipient is preferred over the site admin email.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_list_default_recipient
	 */
	public function test_get_song_list_default_recipient_uses_stored_value_when_present(): void {
		$settings = $this->make_settings();
		$this->set_option( 'song_list_default_recipient', 'stored@example.com' );
		$this->set_option( 'admin_email', 'admin@example.com' );

		$this->assertSame( 'stored@example.com', $settings->get_song_list_default_recipient() );
	}

	/**
	 * The site admin email is used when no default recipient has been stored.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_song_list_default_recipient
	 */
	public function test_get_song_list_default_recipient_falls_back_to_admin_email(): void {
		$settings = $this->make_settings();
		$this->set_option( 'admin_email', 'admin@example.com' );

		$this->assertSame( 'admin@example.com', $settings->get_song_list_default_recipient() );
	}

	/**
	 * The configured member update recipient is preferred over the site admin email.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_member_update_recipient
	 */
	public function test_get_member_update_recipient_uses_stored_value_when_present(): void {
		$settings = $this->make_settings();
		$this->set_option( 'member_update_recipient', 'updates@example.com' );
		$this->set_option( 'admin_email', 'admin@example.com' );

		$this->assertSame( 'updates@example.com', $settings->get_member_update_recipient() );
	}

	// -- get_wp_content_folder_choices ---------------------------------------------------

	/**
	 * Nested subfolders are listed recursively, keyed by full path with relative-path labels.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_wp_content_folder_choices
	 */
	public function test_get_wp_content_folder_choices_lists_nested_folders_relative_to_base(): void {
		$settings = $this->make_settings();
		$base     = $this->create_temp_directory();
		$this->make_directory_tree(
			$base,
			array(
				'Song A' => array( 'Verses' => array() ),
				'Song B' => array(),
			)
		);

		$choices = $settings->get_wp_content_folder_choices( $base );

		$this->assertSame(
			array(
				$base . '/Song A'        => 'Song A',
				$base . '/Song A/Verses' => 'Song A/Verses',
				$base . '/Song B'        => 'Song B',
			),
			$choices
		);
	}

	/**
	 * A base directory that does not exist yields an empty list of choices.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_wp_content_folder_choices
	 */
	public function test_get_wp_content_folder_choices_returns_empty_array_for_missing_directory(): void {
		$settings = $this->make_settings();

		$this->assertSame( array(), $settings->get_wp_content_folder_choices( $this->create_temp_directory() . '/does-not-exist' ) );
	}

	/**
	 * With no base directory argument, the configured song library directory is used.
	 *
	 * @covers \DmbcTools\DmbcSettings::get_wp_content_folder_choices
	 */
	public function test_get_wp_content_folder_choices_defaults_to_song_library_directory(): void {
		$settings = $this->make_settings();

		$choices = $settings->get_wp_content_folder_choices();

		$base = WP_CONTENT_DIR . '/dmbc-song-library';
		$this->assertSame(
			array(
				$base . '/Song A'        => 'Song A',
				$base . '/Song A/Verses' => 'Song A/Verses',
				$base . '/Song B'        => 'Song B',
			),
			$choices
		);
	}

	// -- ajax_browse_directory ------------------------------------------------------------

	/**
	 * Users lacking the manage_options capability receive a JSON error response.
	 *
	 * @covers \DmbcTools\DmbcSettings::ajax_browse_directory
	 */
	public function test_ajax_browse_directory_rejects_users_without_permission(): void {
		$settings = $this->make_settings();
		$this->set_current_user_can( false );

		try {
			$settings->ajax_browse_directory();
			$this->fail( 'Expected a JSON error response to be sent.' );
		} catch ( Dmbc_Test_Json_Response_Exception $exception ) {
			$this->assertFalse( $exception->response_success );
		}
	}

	/**
	 * An invalid nonce causes the request to be aborted via wp_die().
	 *
	 * @covers \DmbcTools\DmbcSettings::ajax_browse_directory
	 */
	public function test_ajax_browse_directory_rejects_invalid_nonce(): void {
		$settings = $this->make_settings();
		$this->set_nonce_valid( false );

		$this->expectException( Dmbc_Test_Wp_Die_Exception::class );

		$settings->ajax_browse_directory();
	}

	/**
	 * An empty path lists the top-level contents of WP_CONTENT_DIR.
	 *
	 * @covers \DmbcTools\DmbcSettings::ajax_browse_directory
	 */
	public function test_ajax_browse_directory_lists_wp_content_by_default(): void {
		$settings      = $this->make_settings();
		$_POST['path'] = '';

		try {
			$settings->ajax_browse_directory();
			$this->fail( 'Expected a JSON success response to be sent.' );
		} catch ( Dmbc_Test_Json_Response_Exception $exception ) {
			$this->assertTrue( $exception->response_success );
			$this->assertSame( WP_CONTENT_DIR, $exception->response_data['path'] );
			$this->assertContains( 'dmbc-song-library', $exception->response_data['directories'] );
		}
	}

	/**
	 * A relative path resolves under WP_CONTENT_DIR and reports its subdirectories and parent.
	 *
	 * @covers \DmbcTools\DmbcSettings::ajax_browse_directory
	 */
	public function test_ajax_browse_directory_lists_subdirectories_of_relative_path(): void {
		$settings      = $this->make_settings();
		$_POST['path'] = 'dmbc-song-library';

		try {
			$settings->ajax_browse_directory();
			$this->fail( 'Expected a JSON success response to be sent.' );
		} catch ( Dmbc_Test_Json_Response_Exception $exception ) {
			$this->assertTrue( $exception->response_success );
			$this->assertSame( WP_CONTENT_DIR . '/dmbc-song-library', $exception->response_data['path'] );
			$this->assertSame( WP_CONTENT_DIR, $exception->response_data['parent'] );
			$this->assertSame( array( 'Song A', 'Song B' ), $exception->response_data['directories'] );
		}
	}

	/**
	 * A path that does not exist on disk produces a JSON error response.
	 *
	 * @covers \DmbcTools\DmbcSettings::ajax_browse_directory
	 */
	public function test_ajax_browse_directory_returns_error_for_nonexistent_path(): void {
		$settings      = $this->make_settings();
		$_POST['path'] = 'this-folder-does-not-exist';

		try {
			$settings->ajax_browse_directory();
			$this->fail( 'Expected a JSON error response to be sent.' );
		} catch ( Dmbc_Test_Json_Response_Exception $exception ) {
			$this->assertFalse( $exception->response_success );
		}
	}

	// -- register_settings ------------------------------------------------------------------

	/**
	 * All four options are registered with correct defaults and functioning sanitize callbacks.
	 *
	 * @covers \DmbcTools\DmbcSettings::register_settings
	 */
	public function test_register_settings_registers_options_with_working_sanitize_callbacks(): void {
		$settings = $this->make_settings();

		$settings->register_settings();

		$registered = $GLOBALS['dmbc_test_state']['registered_settings'];

		$this->assertArrayHasKey( 'song_library_directory', $registered );
		$this->assertSame( 'dmbc-song-library', $registered['song_library_directory']['args']['default'] );
		$this->assertSame(
			'some/path',
			call_user_func( $registered['song_library_directory']['args']['sanitize_callback'], 'some\\path\\' )
		);

		$this->assertArrayHasKey( 'song_library_exclusion_regexes', $registered );
		$this->assertSame(
			array( 'foo' ),
			call_user_func( $registered['song_library_exclusion_regexes']['args']['sanitize_callback'], 'foo' )
		);

		$this->assertArrayHasKey( 'song_list_recipient_roles', $registered );
		$this->assertArrayHasKey( 'song_list_default_recipient', $registered );
		$this->assertArrayHasKey( 'member_update_recipient', $registered );
		$this->assertArrayHasKey( 'remove_data_on_uninstall', $registered );
		$this->assertFalse( $registered['remove_data_on_uninstall']['args']['default'] );
		$this->assertSame(
			'',
			call_user_func( $registered['song_list_default_recipient']['args']['sanitize_callback'], 'invalid-email' )
		);
	}

	/**
	 * The settings sections and fields are registered under the expected sections.
	 *
	 * @covers \DmbcTools\DmbcSettings::register_settings
	 */
	public function test_register_settings_registers_expected_sections_and_fields(): void {
		$settings = $this->make_settings();

		$settings->register_settings();

		$this->assertArrayHasKey( 'general_section', $GLOBALS['dmbc_test_state']['settings_sections'] );
		$this->assertArrayHasKey( 'notifications_section', $GLOBALS['dmbc_test_state']['settings_sections'] );
		$this->assertArrayHasKey( 'member_update_notifications_section', $GLOBALS['dmbc_test_state']['settings_sections'] );

		$fields = $GLOBALS['dmbc_test_state']['settings_fields'];
		$this->assertArrayHasKey( 'song_library_directory', $fields );
		$this->assertSame( 'general_section', $fields['song_library_directory']['section'] );
		$this->assertArrayHasKey( 'remove_data_on_uninstall', $fields );
		$this->assertSame( 'general_section', $fields['remove_data_on_uninstall']['section'] );
		$this->assertArrayHasKey( 'song_list_recipient_roles', $fields );
		$this->assertSame( 'notifications_section', $fields['song_list_recipient_roles']['section'] );
		$this->assertArrayHasKey( 'member_update_recipient', $fields );
		$this->assertSame( 'member_update_notifications_section', $fields['member_update_recipient']['section'] );
	}
}
