<?php

require_once dirname( __DIR__ ) . '/src/songlist-table.php';
require_once dirname( __DIR__ ) . '/src/songlist-view.php';

use DmbcTools\DmbcSettings;
use DmbcTools\Plugin;
use DmbcTools\SongListView;

/** @covers \DmbcTools\SongListView */
final class SongListViewTest extends DmbcUnitTestBase {
	private function make_view(): SongListView {
		return new SongListView( new DmbcSettings() );
	}

	private function make_post( int $id = 21 ): \WP_Post {
		$post                                       = new \WP_Post( $id );
		$post->post_title                           = 'September rehearsal';
		$post->post_type                            = Plugin::SONGLIST_POST_TYPE;
		$post->post_content                         = 'Start with warmups.';
		$post->post_excerpt                         = 'Warmups.';
		$GLOBALS['dmbc_test_state']['posts'][ $id ] = $post;
		$this->set_post_meta( $id, Plugin::PERFORMANCE_DATE_META_KEY, '2026-09-02' );
		$this->set_post_meta( $id, Plugin::SONGS_META_KEY, array() );
		$this->set_post_meta( $id, Plugin::NOTES_META_KEY, 'Bring folders.' );
		return $post;
	}

	public function test_constructor_registers_table_creation_and_create_song_list_table_is_repeatable(): void {
		$view = $this->make_view();
		$this->assertCount( 1, $this->get_registered_actions( 'admin_menu' ) );
		$view->create_song_list_table();
		$view->create_song_list_table();
		$this->assertTrue( true );
	}

	public function test_path_conversion_and_song_folder_choices(): void {
		$library = $this->create_temp_directory();
		$this->make_directory_tree(
			$library,
			array(
				'Song A'         => array( 'Verses' => array() ),
				'Archived Music' => array( 'Old' => array() ),
			)
		);
		$this->set_option( 'song_library_directory', $library );

		$this->assertSame( 'Song A/Verses', SongListView::convert_full_path_to_relative( 'C:\\library', 'C:\\library\\Song A\\Verses' ) );
		$this->assertSame(
			array(
				$library . '/Song A'        => 'Song A',
				$library . '/Song A/Verses' => 'Song A/Verses',
			),
			$this->make_view()->get_song_folder_choices()
		);
	}

	public function test_song_list_view_renderers_return_and_echo_expected_content(): void {
		$this->make_post();
		$view = $this->make_view();

		$this->assertStringContainsString( 'September rehearsal for 2026-09-02', $view->render_song_list_view_page( 21 ) );
		ob_start();
		$view->dmbc_render_song_list_view_page( 21 );
		$this->assertStringContainsString( 'September rehearsal', (string) ob_get_clean() );
	}

	public function test_song_list_view_returns_login_message_when_logged_out(): void {
		$GLOBALS['dmbc_test_state']['logged_in'] = false;
		$this->assertSame( '<p>Please log in to view this song list.</p>', $this->make_view()->render_song_list_view_page() );
	}

	public function test_edit_and_delete_page_renderers_return_forms(): void {
		$this->make_post();
		$this->set_option( 'song_library_directory', $this->create_temp_directory() . '/missing' );
		$view = $this->make_view();

		$this->assertStringContainsString( 'dmbc_song_list_title', $view->render_song_list_edit_page() );
		$this->assertStringContainsString( 'dmbc_song_list_delete_nonce', $view->dmbc_render_song_list_delete_page() );
		ob_start();
		$view->dmbc_render_songlist_edit_page();
		$this->assertStringContainsString( 'Add Rehearsal Song List', (string) ob_get_clean() );
	}

	public function test_edit_page_populates_saved_song_list_metadata(): void {
		$post    = $this->make_post();
		$library = $this->create_temp_directory();
		$this->make_directory_tree(
			$library,
			array(
				'Song A' => array(),
				'Song B' => array(),
			)
		);
		$this->set_option( 'song_library_directory', $library );
		$this->set_post_meta( $post->ID, Plugin::SONGS_META_KEY, array( 'Song A', 'Song B' ) );
		$this->set_post_meta( $post->ID, Plugin::PERFORMANCE_DATE_META_KEY, '2026-09-09' );
		$this->set_post_meta( $post->ID, Plugin::NOTES_META_KEY, 'Bring folders.' );

		$html = $this->make_view()->render_song_list_edit_page( $post->ID );

		$this->assertStringContainsString( 'value="September rehearsal"', $html );
		$this->assertStringContainsString( 'value="2026-09-09"', $html );
		$this->assertStringContainsString( 'value="Song A"', $html );
		$this->assertStringContainsString( 'value="Song B"', $html );
		$this->assertStringContainsString( 'Bring folders.', $html );
	}

	public function test_table_page_renderers_and_admin_route_render_member_table(): void {
		$this->make_post();
		$view = $this->make_view();

		$this->assertStringContainsString( 'Rehearsal Song Lists', $view->render_member_song_lists_table_page() );
		$this->assertStringContainsString( 'Rehearsal Song Lists', $view->render_song_list_table_page() );
		ob_start();
		$view->dmbc_render_songlist_table_page();
		$this->assertStringContainsString( 'Rehearsal Song Lists', (string) ob_get_clean() );
		ob_start();
		$view->dmbc_render_song_list_table_page();
		$this->assertStringContainsString( 'Rehearsal Song Lists', (string) ob_get_clean() );
	}

	public function test_table_page_rejects_invalid_nonce_and_admin_edit_route_renders_form(): void {
		$this->make_post();
		$this->set_option( 'song_library_directory', $this->create_temp_directory() . '/missing' );
		$view = $this->make_view();
		$_GET = array(
			'song_list_id' => '21',
			'_wpnonce'     => 'invalid',
		);
		$this->set_wp_verify_nonce_result( false );
		$this->assertSame( '<p>Invalid song list request.</p>', $view->render_song_list_table_page() );

		$this->set_wp_verify_nonce_result( true );
		$_GET = array(
			'song_list_id' => '21',
			'action'       => 'edit',
			'_wpnonce'     => 'valid',
		);
		$this->assertStringContainsString( 'Update Rehearsal Song List', $view->dmbc_render_song_lists_admin_page() );
	}

	public function test_delete_handler_and_form_handler_ignore_invalid_requests(): void {
		$view = $this->make_view();
		$view->handle_delete_song_list_form();
		$view->handle_song_list_form();
		$this->assertSame( array(), $this->get_registered_actions( 'admin_notices' ) );
	}

	public function test_handle_song_list_form_stores_a_new_post_and_its_metadata(): void {
		$library = $this->create_temp_directory();
		$this->set_option( 'song_library_directory', $library );
		$_POST = array(
			'dmbc_song_list_nonce'  => 'valid-nonce',
			'dmbc_song_list_title'  => 'September rehearsal',
			'dmbc_notes'            => 'Begin with warmups.',
			'dmbc_performance_date' => '2026-09-09',
			'dmbc_song_list_songs'  => array( $library . '/Song A', $library . '/Song B' ),
		);

		$this->make_view()->handle_song_list_form();

		$this->assertArrayHasKey( 1, $GLOBALS['dmbc_test_state']['posts'] );
		$this->assertSame( 'September rehearsal', $GLOBALS['dmbc_test_state']['posts'][1]->post_title );
		$this->assertSame( Plugin::SONGLIST_POST_TYPE, $GLOBALS['dmbc_test_state']['posts'][1]->post_type );
		$this->assertSame( 'Begin with warmups.', $GLOBALS['dmbc_test_state']['posts'][1]->post_content );
		$this->assertSame( array( 'Song A', 'Song B' ), $this->get_stored_post_meta( 1, Plugin::SONGS_META_KEY ) );
		$this->assertSame( '2026-09-09', $this->get_stored_post_meta( 1, Plugin::PERFORMANCE_DATE_META_KEY ) );
		$this->assertSame( 'Begin with warmups.', $this->get_stored_post_meta( 1, Plugin::NOTES_META_KEY ) );
	}

	public function test_send_methods_return_false_without_a_valid_song_list(): void {
		$this->set_option( 'song_list_default_recipient', 'director@example.com' );
		$view = $this->make_view();

		$this->assertFalse( $view->send_song_list_to_roles( 999, array() ) );
		$this->assertFalse( $view->send_song_list_to_role( 999, 'editor' ) );
	}
}
