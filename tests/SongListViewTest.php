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
		$this->assertStringContainsString( 'name="dmbc_song_list_nonce"', $view->render_song_list_edit_page() );
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
		$this->set_post_meta(
			$post->ID,
			Plugin::SONGS_META_KEY,
			array(
				array(
					'type'  => 'song',
					'value' => 'Song A',
				),
				array(
					'type'  => 'note',
					'value' => 'Ten-minute break',
				),
				'Song B',
			)
		);
		$this->set_post_meta( $post->ID, Plugin::PERFORMANCE_DATE_META_KEY, '2026-09-09' );
		$this->set_post_meta( $post->ID, Plugin::NOTES_META_KEY, 'Bring folders.' );

		$html = $this->make_view()->render_song_list_edit_page( $post->ID );

		$this->assertStringContainsString( 'value="September rehearsal"', $html );
		$this->assertStringContainsString( 'value="2026-09-09"', $html );
		$this->assertStringContainsString( 'value="Song A"', $html );
		$this->assertStringContainsString( 'value="Song B"', $html );
		$this->assertStringContainsString( 'data-type="note"', $html );
		$this->assertStringContainsString( 'Note: Ten-minute break', $html );
		$this->assertStringContainsString( 'Bring folders.', $html );
	}

	public function test_view_page_links_songs_and_renders_notes_as_plain_text(): void {
		$post = $this->make_post();
		$this->set_post_meta(
			$post->ID,
			Plugin::SONGS_META_KEY,
			array(
				array(
					'type'  => 'song',
					'value' => 'Song A',
				),
				array(
					'type'  => 'note',
					'value' => 'Ten-minute break',
				),
			)
		);

		$html = $this->make_view()->render_song_list_view_page( $post->ID );

		$this->assertStringContainsString( '>Song A</a>', $html );
		$this->assertStringContainsString( 'http://example.test/wp-content/', $html );
		$this->assertStringContainsString( 'Ten-minute break', $html );
		$this->assertStringNotContainsString( '>Ten-minute break</a>', $html );
	}

	public function test_admin_edit_page_loads_the_song_list_id_from_the_request(): void {
		$post    = $this->make_post();
		$library = $this->create_temp_directory();
		$this->make_directory_tree( $library, array( 'Song A' => array() ) );
		$this->set_option( 'song_library_directory', $library );
		$this->set_post_meta( $post->ID, Plugin::SONGS_META_KEY, array( 'Song A' ) );
		$_GET = array(
			'song_list_id' => (string) $post->ID,
			'_wpnonce'     => 'valid',
		);

		ob_start();
		$this->make_view()->dmbc_render_songlist_edit_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'value="September rehearsal"', $html );
		$this->assertStringContainsString( 'value="21"', $html );
	}

	public function test_table_page_renderers_and_admin_route_render_member_table(): void {
		$this->make_post();
		$view = $this->make_view();

		$this->assertStringContainsString( 'Rehearsal Song Lists', $view->generate_member_song_lists_table_page() );
		$this->assertStringContainsString( 'name="_wpnonce"', $view->generate_member_song_lists_table_page() );
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

	public function test_table_page_rejects_missing_and_malformed_nonces(): void {
		$view = $this->make_view();
		$_GET = array( 'song_list_id' => '21' );
		$this->assertSame( '<p>Invalid song list request.</p>', $view->render_song_list_table_page() );
		$_GET['_wpnonce'] = array( 'invalid' );
		$this->assertSame( '<p>Invalid song list request.</p>', $view->render_song_list_table_page() );
	}

	public function test_admin_and_delete_page_entry_points_reject_missing_request_nonce(): void {
		$_GET = array( 'song_list_id' => '21' );
		$view = $this->make_view();

		$this->assertSame( '<p>Invalid song list request.</p>', $view->dmbc_render_song_lists_admin_page() );
		$this->assertSame( '<p>Invalid song list request.</p>', $view->dmbc_render_song_list_delete_page() );
		ob_start();
		$view->dmbc_render_songlist_edit_page();
		$this->assertSame( '<p>Invalid song list request.</p>', (string) ob_get_clean() );
	}

	public function test_admin_table_callback_routes_a_valid_delete_request_to_confirmation(): void {
		$this->make_post();
		$_GET = array(
			'song_list_id' => '21',
			'action'       => 'delete',
			'_wpnonce'     => 'valid',
		);
		ob_start();
		$this->make_view()->dmbc_render_songlist_table_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="dmbc_delete_song_list_form"', $html );
		$this->assertStringContainsString( 'name="dmbc_song_list_delete_nonce"', $html );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['wp_delete_post_calls'] ?? array() );
	}

	public static function invalid_form_nonces(): array {
		$cases = array();
		foreach ( array( 'save', 'delete', 'direct-delete' ) as $handler ) {
			foreach ( array( null, '', 'invalid', array( 'invalid' ) ) as $nonce ) {
				$cases[] = array( $handler, $nonce );
			}
		}
		return $cases;
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalid_form_nonces' )]
	public function test_form_handlers_reject_invalid_nonces_before_mutating_posts( string $handler, mixed $nonce ): void {
		$view = $this->make_view();
		$post = $this->make_post();
		$_POST = array( 'dmbc_song_list_id' => (string) $post->ID );
		$nonce_name = 'dmbc_song_list_nonce';
		if ( 'save' === $handler ) {
			$_POST['dmbc_song_list_title'] = 'Changed title';
		} else {
			$_POST['dmbc_delete_song_list'] = 'Delete';
			$nonce_name = 'dmbc_song_list_delete_nonce';
		}
		if ( null !== $nonce ) {
			$_POST[ $nonce_name ] = $nonce;
		}
		$this->set_wp_verify_nonce_result( false );

		try {
			if ( 'direct-delete' === $handler ) {
				$view->handle_delete_song_list_form();
			} else {
				$view->handle_song_list_form();
			}
			$this->fail( 'An invalid nonce must abort the song list submission.' );
		} catch ( Dmbc_Test_Wp_Die_Exception $exception ) {
			$this->assertStringContainsString( 'Invalid song list request.', $exception->getMessage() );
		}

		$this->assertSame( 'September rehearsal', $post->post_title );
		$this->assertSame( array(), $this->get_registered_actions( 'admin_notices' ) );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['mail_calls'] );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['wp_delete_post_calls'] ?? array() );
	}

	public function test_delete_handler_verifies_the_delete_nonce_once(): void {
		$_POST = array(
			'dmbc_delete_song_list'       => 'Delete',
			'dmbc_song_list_id'           => '21',
			'dmbc_song_list_delete_nonce' => 'valid-delete-nonce',
		);
		$this->make_view()->handle_song_list_form();

		$this->assertSame(
			array( array( 'nonce' => 'valid-delete-nonce', 'action' => 'dmbc_delete_song_list' ) ),
			$GLOBALS['dmbc_test_state']['wp_verify_nonce_calls']
		);
		$this->assertCount( 1, $this->get_registered_actions( 'admin_notices' ) );
		$this->assertSame(
			array( array( 'post_id' => 21, 'force_delete' => true ) ),
			$GLOBALS['dmbc_test_state']['wp_delete_post_calls']
		);
	}

	public function test_handle_song_list_form_stores_a_new_post_and_its_metadata(): void {
		$library = $this->create_temp_directory();
		$this->set_option( 'song_library_directory', $library );
		$_POST = array(
			'dmbc_song_list_nonce'  => 'valid-nonce',
			'dmbc_song_list_title'  => 'September rehearsal',
			'dmbc_notes'            => 'Begin with warmups.',
			'dmbc_performance_date' => '2026-09-09',
			'dmbc_rehearsal_items'  => array(
				array(
					'type'  => 'song',
					'value' => $library . '/Song A',
				),
				array(
					'type'  => 'note',
					'value' => 'Ten-minute break',
				),
				array(
					'type'  => 'song',
					'value' => $library . '/Song B',
				),
			),
		);

		$this->make_view()->handle_song_list_form();

		$this->assertArrayHasKey( 1, $GLOBALS['dmbc_test_state']['posts'] );
		$this->assertSame( 'September rehearsal', $GLOBALS['dmbc_test_state']['posts'][1]->post_title );
		$this->assertSame( Plugin::SONGLIST_POST_TYPE, $GLOBALS['dmbc_test_state']['posts'][1]->post_type );
		$this->assertSame( 'Begin with warmups.', $GLOBALS['dmbc_test_state']['posts'][1]->post_content );
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
			$this->get_stored_post_meta( 1, Plugin::SONGS_META_KEY )
		);
		$this->assertSame( '2026-09-09', $this->get_stored_post_meta( 1, Plugin::PERFORMANCE_DATE_META_KEY ) );
		$this->assertSame( 'Begin with warmups.', $this->get_stored_post_meta( 1, Plugin::NOTES_META_KEY ) );
		$this->assertSame(
			array( array( 'nonce' => 'valid-nonce', 'action' => 'dmbc_create_song_list' ) ),
			$GLOBALS['dmbc_test_state']['wp_verify_nonce_calls']
		);

		$_POST['dmbc_song_list_id']    = '1';
		$_POST['dmbc_song_list_title'] = 'Updated rehearsal';
		$this->make_view()->handle_song_list_form();
		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['posts'] );
		$this->assertSame( 'Updated rehearsal', $GLOBALS['dmbc_test_state']['posts'][1]->post_title );
	}

	public function test_song_list_email_matches_the_web_page_items_and_preserves_notes(): void {
		$post = $this->make_post();
		$GLOBALS['dmbc_test_state']['logged_in'] = false;
		$GLOBALS['dmbc_test_state']['users'] = array(
			(object) array( 'user_email' => 'member@example.com', 'role' => 'um_member' ),
		);
		$this->set_option( Plugin::OPTION_EMAIL_RECIPIENT, 'director@example.com' );
		$this->set_post_meta(
			$post->ID,
			Plugin::SONGS_META_KEY,
			array(
				'Song A',
				array( 'type' => 'note', 'value' => 'Ten-minute break <strong>rest</strong>' ),
				array( 'type' => 'song', 'value' => 'Song B' ),
			)
		);

		$recipients = $this->make_view()->send_song_list_to_roles( $post->ID, array( 'um_member' ) );
		$this->assertSame( array( 'member@example.com' ), $recipients );
		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['mail_calls'] );
		$mail = $GLOBALS['dmbc_test_state']['mail_calls'][0];
		$this->assertSame( 'director@example.com', $mail['recipients'] );
		$this->assertSame( 'Rehearsal song list: 2026-09-02', $mail['subject'] );
		$this->assertContains( 'Bcc: member@example.com', $mail['headers'] );
		$this->assertContains( 'Content-Type: text/html; charset=UTF-8', $mail['headers'] );
		$this->assertStringContainsString( 'September rehearsal for 2026-09-02', $mail['message'] );
		$this->assertStringContainsString( 'Rehearsal items', $mail['message'] );
		$this->assertStringContainsString( '>Song A</a>', $mail['message'] );
		$this->assertStringContainsString( 'href="http://example.test/wp-content/', $mail['message'] );
		$this->assertStringContainsString( 'Ten-minute break &lt;strong&gt;rest&lt;/strong&gt;', $mail['message'] );
		$this->assertStringNotContainsString( 'Ten-minute break <strong>', $mail['message'] );
		$this->assertStringContainsString( 'Start with warmups.', $mail['message'] );
		$this->assertLessThan( strpos( $mail['message'], 'Ten-minute break' ), strpos( $mail['message'], '>Song A</a>' ) );
		$this->assertLessThan( strpos( $mail['message'], '>Song B</a>' ), strpos( $mail['message'], 'Ten-minute break' ) );
		$this->assertStringNotContainsString( 'Please log in', $mail['message'] );
	}

	public function test_song_list_email_renders_the_empty_list_message(): void {
		$post = $this->make_post();
		$GLOBALS['dmbc_test_state']['users'] = array(
			(object) array( 'user_email' => 'member@example.com', 'role' => 'um_member' ),
		);
		$this->set_option( Plugin::OPTION_EMAIL_RECIPIENT, 'director@example.com' );
		$this->make_view()->send_song_list_to_role( $post->ID, 'um_member' );
		$this->assertStringContainsString( 'No songs selected for this list.', $GLOBALS['dmbc_test_state']['mail_calls'][0]['message'] );
	}

	public function test_send_methods_return_false_without_a_valid_song_list(): void {
		$GLOBALS['dmbc_test_state']['users']     = array(
			(object) array( 'user_email' => 'subscriber@example.com','role' => 'subscriber' ),
			(object) array( 'user_email' => 'editor@example.com','role' => 'editor' ),
		);

		$this->set_option( Plugin::OPTION_EMAIL_RECIPIENT, 'director@example.com' );
		$view = $this->make_view();

		$this->assertEmpty( $view->send_song_list_to_roles( 999, array() ) );
		$this->assertEmpty($view->send_song_list_to_roles( 999, array('editor','subscriber') )
		);
	}
}
