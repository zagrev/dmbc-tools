<?php

use DmbcTools\Plugin;
use DmbcTools\SongListTable;
use DmbcTools\DeluxeCcTransaction;
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
		$this->assertTrue( $registered['dmbc-songlist']['public'] );
		$this->assertSame( array( 'slug' => 'songlists' ), $registered['dmbc-songlist']['rewrite'] );
	}

	/**
	 * Single song-list requests use the plugin's frontend template.
	 *
	 * @covers \DmbcTools\Plugin::dmbc_single_songlist_template
	 */
	public function test_single_songlist_template_returns_plugin_template_for_songlist_posts(): void {
		$plugin = Plugin::instance();
		$this->set_current_singular_post_type( Plugin::SONGLIST_POST_TYPE );

		$template = $plugin->dmbc_single_songlist_template( '/theme/single.php' );

		$this->assertSame(
			str_replace( '\\', '/', dirname( __DIR__ ) ) . '/src/templates/single-songlist.php',
			str_replace( '\\', '/', $template )
		);
	}

	/**
	 * Song-list archive requests use the plugin's member-facing archive template.
	 *
	 * @covers \DmbcTools\Plugin::dmbc_single_songlist_template
	 */
	public function test_songlist_archive_template_returns_plugin_archive_template(): void {
		$plugin = Plugin::instance();
		$this->set_current_post_type_archive( Plugin::SONGLIST_POST_TYPE );

		$template = $plugin->dmbc_single_songlist_template( '/theme/archive.php' );

		$this->assertSame(
			str_replace( '\\', '/', dirname( __DIR__ ) ) . '/src/templates/archive-songlist.php',
			str_replace( '\\', '/', $template )
		);
	}

	/**
	 * The public song-list archive is ordered by rehearsal date, newest first.
	 *
	 * @covers \DmbcTools\Plugin::order_songlist_archive_query
	 */
	public function test_order_songlist_archive_query_orders_by_rehearsal_date_descending(): void {
		$GLOBALS['dmbc_test_state']['is_admin'] = false;
		$query                                = new class() extends WP_Query {
			public array $query_vars = array();

			public function is_main_query(): bool {
				return true;
			}

			public function is_post_type_archive( $post_types = '' ): bool {
				return Plugin::SONGLIST_POST_TYPE === $post_types;
			}

			public function set( $query_var, $value ): void {
				$this->query_vars[ $query_var ] = $value;
			}
		};

		Plugin::instance()->order_songlist_archive_query( $query );

		$this->assertSame( Plugin::PERFORMANCE_DATE_META_KEY, $query->query_vars['meta_key'] );
		$this->assertSame( 'meta_value', $query->query_vars['orderby'] );
		$this->assertSame( 'DESC', $query->query_vars['order'] );
	}

	/**
	 * Legacy capability cleanup removes stale caps from roles and users and unregisters the old menu type.
	 *
	 * @covers \DmbcTools\Plugin::unregister_old_post_types
	 */
	public function test_unregister_old_post_types_removes_legacy_caps_and_unregisters_post_type(): void {
		$GLOBALS['dmbc_test_state']['registered_post_types']['rehearsal-notes'] = array();
		$GLOBALS['dmbc_test_state']['menu_pages']['rehearsal-notes'] = array( 'slug' => 'rehearsal-notes' );
		$GLOBALS['dmbc_test_state']['roles']['editor'] = array(
			'view-song-lists' => true,
			'edit_song_list'  => true,
		);
		$GLOBALS['dmbc_test_state']['users'][] = new WP_User( 7, array( 'caps' => array( 'view-song-lists' => true, 'edit_song_list' => true ) ) );

		Plugin::instance()->unregister_old_post_types();

		$this->assertArrayNotHasKey( 'rehearsal-notes', $GLOBALS['dmbc_test_state']['registered_post_types'] );
		$this->assertArrayNotHasKey( 'rehearsal-notes', $GLOBALS['dmbc_test_state']['menu_pages'] );
		$this->assertArrayNotHasKey( 'view-song-lists', $GLOBALS['dmbc_test_state']['roles']['editor'] );
		$this->assertArrayNotHasKey( 'edit_song_list', $GLOBALS['dmbc_test_state']['roles']['editor'] );
		$this->assertArrayNotHasKey( 'view-song-lists', $GLOBALS['dmbc_test_state']['users'][0]->caps );
		$this->assertArrayNotHasKey( 'edit_song_list', $GLOBALS['dmbc_test_state']['users'][0]->caps );
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
	 * Member updates are registered as a private custom post type.
	 *
	 * @covers \DmbcTools\Plugin::register_member_update_type
	 */
	public function test_register_member_update_type_registers_the_custom_post_type(): void {
		$plugin = Plugin::instance();
		$this->set_existing_post_types( array() );

		$plugin->register_member_update_type();

		$registered = $GLOBALS['dmbc_test_state']['registered_post_types'];
		$this->assertArrayHasKey( Plugin::MEMBER_UPDATE_POST_TYPE, $registered );
		$this->assertSame( 'Member Updates', $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['labels']['name'] );
		$this->assertTrue( $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['public'] );
		$this->assertTrue( $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['show_ui'] );
		$this->assertFalse( $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['show_in_menu'] );
		$this->assertTrue( $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['has_archive'] );
		$this->assertSame( array( 'slug' => 'member-updates' ), $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['rewrite'] );
		$this->assertSame( Plugin::CAP_EDIT_MEMBER_UPDATES, $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['capabilities']['edit_post'] );
		$this->assertSame( Plugin::CAP_EDIT_MEMBER_UPDATES, $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['capabilities']['edit_published_posts'] );
		$this->assertSame( Plugin::CAP_EDIT_MEMBER_UPDATES, $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['capabilities']['edit_others_posts'] );
		$this->assertSame( Plugin::CAP_EDIT_MEMBER_UPDATES, $registered[ Plugin::MEMBER_UPDATE_POST_TYPE ]['capabilities']['create_posts'] );
	}

	/**
	 * Song-list block templates are loaded from Gutenberg HTML template files.
	 *
	 * @covers \DmbcTools\Plugin::register_songlist_templates
	 */
	public function test_register_songlist_templates_loads_html_templates_for_canonical_slug(): void {
		$templates = Plugin::instance()->register_songlist_templates( array(), array( 'slug__in' => array( 'single-dmbc-songlist' ) ), 'wp_template' );

		$this->assertCount( 1, $templates );
		$this->assertSame( 'single-dmbc-songlist', $templates[0]->slug );
		$this->assertSame( 'Single Songlist', $templates[0]->title );
		$this->assertStringContainsString( '[dmbc_songlist]', $templates[0]->content );
	}

	/**
	 * Song-list templates tolerate theme-qualified lookup keys.
	 *
	 * @covers \DmbcTools\Plugin::register_songlist_templates
	 */
	public function test_register_songlist_templates_loads_html_templates_for_theme_qualified_slug(): void {
		$templates = Plugin::instance()->register_songlist_templates( array(), array( 'slug__in' => array( 'dmbc-tools/single-songlist' ) ), 'wp_template' );

		$this->assertCount( 1, $templates );
		$this->assertSame( 'single-songlist', $templates[0]->slug );
		$this->assertSame( 'dmbc-tools//single-songlist', $templates[0]->id );
	}

	/**
	 * Member-update block templates are loaded from Gutenberg HTML template files.
	 *
	 * @covers \DmbcTools\Plugin::register_member_update_templates
	 */
	public function test_register_member_update_templates_loads_html_templates_for_canonical_slug(): void {
		$templates = Plugin::instance()->register_member_update_templates( array(), array( 'slug__in' => array( 'single-dmbc-member-updates' ) ), 'wp_template' );

		$this->assertCount( 1, $templates );
		$this->assertSame( 'single-dmbc-member-updates', $templates[0]->slug );
		$this->assertSame( 'Single Member Update', $templates[0]->title );
		$this->assertStringContainsString( '<main class="wp-block-group dmbc-member-update-container">', $templates[0]->content );
	}

	/**
	 * Member-update templates tolerate theme-qualified lookup keys.
	 *
	 * @covers \DmbcTools\Plugin::register_member_update_templates
	 */
	public function test_register_member_update_templates_loads_html_templates_for_theme_qualified_slugs(): void {
		$single_template = Plugin::instance()->register_member_update_templates( array(), array( 'slug__in' => array( 'dmbc-tools/single-member-update' ) ), 'wp_template' );
		$archive_template = Plugin::instance()->register_member_update_templates( array(), array( 'slug__in' => array( 'dmbc-tools/archive-member-update' ) ), 'wp_template' );

		$this->assertCount( 1, $single_template );
		$this->assertSame( 'single-member-update', $single_template[0]->slug );
		$this->assertSame( 'dmbc-tools//single-member-update', $single_template[0]->id );
		$this->assertStringContainsString( '<main class="wp-block-group dmbc-member-update-container">', $single_template[0]->content );

		$this->assertCount( 1, $archive_template );
		$this->assertSame( 'archive-member-update', $archive_template[0]->slug );
		$this->assertSame( 'dmbc-tools//archive-member-update', $archive_template[0]->id );
		$this->assertStringContainsString( '<main class="wp-block-group dmbc-member-update-archive">', $archive_template[0]->content );
	}

	/**
	 * Members can manage updates but do not receive song-list editing access.
	 *
	 * @covers \DmbcTools\Plugin::add_songlist_capabilities
	 */
	public function test_um_member_role_receives_only_member_update_management_capabilities(): void {
		$this->define_role( 'um_member', array( Plugin::CAP_EDIT_MEMBER_UPDATES => true ) );

		Plugin::instance()->add_songlist_capabilities();

		$this->assertTrue( $this->role_has_cap( 'um_member', Plugin::CAP_EDIT_MEMBER_UPDATES ) );
		$this->assertTrue( $this->role_has_cap( 'um_member', Plugin::CAP_VIEW_MEMBER_UPDATES ) );
		$this->assertTrue( $this->role_has_cap( 'um_member', Plugin::CAP_PUBLISH_MEMBER_UPDATES ) );
		$this->assertFalse( $this->role_has_cap( 'um_member', Plugin::CAP_EDIT_SONGLIST ) );
	}

	/**
	 * Admin initialization refreshes role capabilities before admin screen permission checks.
	 *
	 * @covers \DmbcTools\Plugin::run
	 */
	public function test_run_registers_admin_capability_refresh(): void {
		$plugin = Plugin::instance();

		$plugin->run();

		$this->assertContains( array( $plugin, 'handle_admin_init' ), $GLOBALS['dmbc_test_state']['actions']['admin_init'] );
	}

	/**
	 * Runtime hook registration wires plugin callbacks without flushing rewrite rules on every request.
	 *
	 * @covers \DmbcTools\Plugin::run
	 */
	public function test_run_registers_core_hooks_without_flushing_rewrite_rules(): void {
		$plugin = Plugin::instance();

		$plugin->run();

		$this->assertContains( array( $plugin, 'initialize' ), $GLOBALS['dmbc_test_state']['actions']['init'] );
		$this->assertContains( array( $plugin, 'register_admin' ), $GLOBALS['dmbc_test_state']['actions']['admin_menu'] );
		$this->assertContains( array( $plugin, 'send_member_update_digest' ), $GLOBALS['dmbc_test_state']['actions'][ Plugin::MEMBER_UPDATE_CRON_HOOK ] );
		$this->assertContains( array( $plugin, 'register_deluxe_cc_notification_route' ), $GLOBALS['dmbc_test_state']['actions']['rest_api_init'] );
		$this->assertSame( array( $plugin, 'render_songlist_shortcode' ), $GLOBALS['dmbc_test_state']['shortcodes']['dmbc_songlist'] );
		$this->assertSame( 0, $GLOBALS['dmbc_test_state']['flush_rewrite_rules_calls'] );
	}

	/**
	 * Initialization registers core plugin objects without flushing rewrite rules on every request.
	 *
	 * @covers \DmbcTools\Plugin::initialize
	 */
	public function test_initialize_registers_content_and_runtime_hooks_without_flushing_rewrite_rules(): void {
		$plugin = Plugin::instance();

		$plugin->initialize();

		$this->assertArrayHasKey( Plugin::SONGLIST_POST_TYPE, $GLOBALS['dmbc_test_state']['registered_post_types'] );
		$this->assertArrayHasKey( Plugin::MEMBER_UPDATE_POST_TYPE, $GLOBALS['dmbc_test_state']['registered_post_types'] );
		$this->assertSame( Plugin::VERSION, get_option( Plugin::OPTION_VERSION ) );
		$this->assertArrayHasKey( Plugin::MEMBER_UPDATE_CRON_HOOK, $GLOBALS['dmbc_test_state']['cron_events'] );
		$this->assertContains( array( $plugin, 'order_songlist_archive_query' ), $GLOBALS['dmbc_test_state']['actions']['pre_get_posts'] );
		$this->assertSame( 0, $GLOBALS['dmbc_test_state']['flush_rewrite_rules_calls'] );
	}

	/**
	 * The user capabilities dashboard widget is registered with the expected callback.
	 *
	 * @covers \DmbcTools\Plugin::register_user_capabilities_dashboard_widget
	 */
	public function test_register_user_capabilities_dashboard_widget_registers_widget(): void {
		$plugin = Plugin::instance();

		$plugin->register_user_capabilities_dashboard_widget();

		$this->assertArrayHasKey( 'wp_user_capabilities_widget', $GLOBALS['dmbc_test_state']['dashboard_widgets'] );
		$this->assertSame( 'Your Current Capabilities', $GLOBALS['dmbc_test_state']['dashboard_widgets']['wp_user_capabilities_widget']['widget_name'] );
		$this->assertSame( array( $plugin, 'render_user_capabilities_widget' ), $GLOBALS['dmbc_test_state']['dashboard_widgets']['wp_user_capabilities_widget']['callback'] );
	}

	/**
	 * The dashboard widget reports when no user is logged in.
	 *
	 * @covers \DmbcTools\Plugin::render_user_capabilities_widget
	 */
	public function test_render_user_capabilities_widget_handles_logged_out_user(): void {
		$GLOBALS['dmbc_test_state']['current_user'] = new WP_User();

		ob_start();
		Plugin::instance()->render_user_capabilities_widget();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'No user logged in.', $html );
	}

	/**
	 * The dashboard widget renders active capabilities and hides disabled caps.
	 *
	 * @covers \DmbcTools\Plugin::render_user_capabilities_widget
	 */
	public function test_render_user_capabilities_widget_lists_active_capabilities(): void {
		$user             = new WP_User( 9 );
		$user->user_login = 'member-one';
		$user->roles      = array( 'um_member' );
		$user->allcaps    = array(
			Plugin::CAP_VIEW_MEMBER_UPDATES => true,
			'disabled_capability'           => false,
		);
		$GLOBALS['dmbc_test_state']['current_user'] = $user;

		ob_start();
		Plugin::instance()->render_user_capabilities_widget();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'member-one', $html );
		$this->assertStringContainsString( 'um_member', $html );
		$this->assertStringContainsString( Plugin::CAP_VIEW_MEMBER_UPDATES, $html );
		$this->assertStringNotContainsString( 'disabled_capability', $html );
	}

	/**
	 * Method register_options() adds the plugin's version option with its default value.
	 *
	 * @covers \DmbcTools\Plugin::register_options
	 */
	public function test_register_options_adds_version_option(): void {
		$plugin = Plugin::instance();

		$plugin->register_options();

		$this->assertSame( Plugin::VERSION, get_option( Plugin::OPTION_VERSION ) );
	}

	/**
	 * Method uninstall() preserves plugin data unless cleanup is explicitly enabled.
	 *
	 * @covers \DmbcTools\Plugin::uninstall
     * @runInSeparateProcess
     * @preserveGlobalState disabled
	 */
	public function test_uninstall_preserves_version_option_when_cleanup_is_disabled(): void {
		$this->set_option( Plugin::OPTION_VERSION, '0.1.0' );
		$this->set_option( Plugin::OPTION_EMAIL_RECIPIENT, 'updates@example.com' );
		$this->set_option( Plugin::OPTION_REMOVE_DATA_ON_UNINSTALL, false );

		if (! \defined( 'WP_UNINSTALL_PLUGIN' )) {
			\define( 'WP_UNINSTALL_PLUGIN', true );
		}
		Plugin::uninstall();

		$this->assertSame( '0.1.0', get_option( Plugin::OPTION_VERSION, false ) );
		$this->assertSame( 'updates@example.com', get_option( Plugin::OPTION_EMAIL_RECIPIENT, false ) );
		$this->assertSame( false, get_option( Plugin::OPTION_REMOVE_DATA_ON_UNINSTALL, false ) );
	}

	/**
	 * Method uninstall() removes plugin options when cleanup is explicitly enabled.
	 *
	 * @covers \DmbcTools\Plugin::uninstall
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
	public function test_uninstall_removes_plugin_options_when_cleanup_is_enabled(): void {
		$this->set_option( Plugin::OPTION_VERSION, '0.1.0' );
		$this->set_option( Plugin::OPTION_EMAIL_RECIPIENT, 'updates@example.com' );
		$this->set_option( Plugin::OPTION_REMOVE_DATA_ON_UNINSTALL, true );

		if (! \defined( 'WP_UNINSTALL_PLUGIN' )) {
			\define( 'WP_UNINSTALL_PLUGIN', true );
		}
		Plugin::uninstall();

		$this->assertFalse( get_option( Plugin::OPTION_VERSION, false ) );
		$this->assertFalse( get_option( Plugin::OPTION_EMAIL_RECIPIENT, false ) );
		$this->assertFalse( get_option( Plugin::OPTION_REMOVE_DATA_ON_UNINSTALL, false ) );
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
	 * The member update digest is scheduled only once and cleared on deactivation.
	 *
	 * @covers \DmbcTools\Plugin::schedule_member_update_digest
	 * @covers \DmbcTools\Plugin::deactivate
	 */
	public function test_member_update_digest_is_scheduled_once_and_cleared_on_deactivation(): void {
		$plugin = Plugin::instance();
		$plugin->schedule_member_update_digest();
		$plugin->schedule_member_update_digest();

		$this->assertSame( 'daily', $GLOBALS['dmbc_test_state']['cron_events'][ Plugin::MEMBER_UPDATE_CRON_HOOK ]['recurrence'] );
		$plugin->deactivate();
		$this->assertArrayNotHasKey( Plugin::MEMBER_UPDATE_CRON_HOOK, $GLOBALS['dmbc_test_state']['cron_events'] );
	}

	/**
	 * Updated published member updates are sent once to all member recipients.
	 *
	 * @covers \DmbcTools\Plugin::send_member_update_digest
	 */
	public function test_member_update_digest_emails_updates_and_records_delivery(): void {
		$update                                  = new \WP_Post( 81 );
		$update->post_type                       = Plugin::MEMBER_UPDATE_POST_TYPE;
		$update->post_title                      = 'Schedule change';
		$update->post_content                    = '<p>Practice starts at 7.</p>';
		$update->post_modified_gmt               = '2026-09-04 12:00:00';
		$GLOBALS['dmbc_test_state']['posts'][81] = $update;
		$GLOBALS['dmbc_test_state']['users']     = array(
			(object) array( 'user_email' => 'member@example.com' ),
			(object) array( 'user_email' => 'member@example.com' ),
		);
		$this->set_option( Plugin::OPTION_EMAIL_RECIPIENT, 'updates@example.com' );

		Plugin::instance()->send_member_update_digest();

		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['mail_calls'] );
		$this->assertSame( 'updates@example.com', $GLOBALS['dmbc_test_state']['mail_calls'][0]['recipients'] );
		$this->assertSame( array( 'Bcc: member@example.com', 'Content-Type: text/html; charset=UTF-8' ), $GLOBALS['dmbc_test_state']['mail_calls'][0]['headers'] );
		$this->assertStringContainsString( 'Schedule change', $GLOBALS['dmbc_test_state']['mail_calls'][0]['message'] );
		$this->assertNotEmpty( $this->get_stored_post_meta( 81, Plugin::MEMBER_UPDATE_SENT_META_KEY ) );

		Plugin::instance()->send_member_update_digest();
		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['mail_calls'] );
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

		$plugin->create_song_list_view();
		$plugin->add_admin_menu();

		$menu_pages = $GLOBALS['dmbc_test_state']['menu_pages'];
		$this->assertArrayHasKey( 'dmbc-songlists-menu', $menu_pages );
		$this->assertSame( Plugin::CAP_VIEW_SONGLISTS, $menu_pages['dmbc-songlists-menu']['capability'] );

		$submenu_pages = $GLOBALS['dmbc_test_state']['submenu_pages'];
		$this->assertArrayHasKey( 'dmbc-songlist-edit', $submenu_pages );
		$this->assertSame( Plugin::CAP_EDIT_SONGLIST, $submenu_pages['dmbc-songlist-edit']['capability'] );
		$this->assertArrayHasKey( 'dmbc-member-updates-menu', $menu_pages );
		$this->assertSame( Plugin::CAP_VIEW_MEMBER_UPDATES, $menu_pages['dmbc-member-updates-menu']['capability'] );
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
	 * Method save_songlist_meta() parses song textarea lines into typed rehearsal items.
	 *
	 * @covers \DmbcTools\Plugin::save_songlist_meta
	 */
	public function test_save_songlist_meta_parses_song_lines_into_rehearsal_items(): void {
		$plugin = Plugin::instance();
		$_POST  = array(
			'dmbc_songlist_meta_nonce' => 'some-nonce',
			'dmbc_songs'               => "Song A\nnote: Ten-minute break\n\nSong B",
		);

		$plugin->save_songlist_meta( 106 );

		$this->assertSame(
			array(
				array(
					'type'  => 'song',
					'value' => 'Song A',
				),
				array(
					'type'  => 'note',
					'value' => 'Ten-minute break',
				),
				array(
					'type'  => 'song',
					'value' => 'Song B',
				),
			),
			$this->get_stored_post_meta( 106, '_dmbc_songs' )
		);
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

	/**
	 * Method register_deluxe_cc_notification_route() registers the POST route with the REST API.
	 *
	 * @covers \DmbcTools\Plugin::register_deluxe_cc_notification_route
	 */
	public function test_register_deluxe_cc_notification_route_registers_expected_route(): void {
		Plugin::instance()->register_deluxe_cc_notification_route();

		$route = $this->get_registered_rest_route( 'dmbc', '/deluxe_cc_notification' );
		$this->assertNotNull( $route );
		$this->assertSame( 'POST', $route['methods'] );
		$this->assertSame( '__return_true', $route['permission_callback'] );
		$this->assertInstanceOf( DeluxeCcTransaction::class, $route['callback'][0] );
		$this->assertSame( 'handle_deluxe_cc_notification', $route['callback'][1] );
	}
}
