<?php

use DmbcTools\Plugin;
use DmbcTools\SongList;
use DmbcTools\SongListPlaylist;

/** @covers \DmbcTools\SongListPlaylist */
final class SongListPlaylistTest extends DmbcUnitTestBase {
	/**
	 * The member check is based on the current logged-in user's role slugs.
	 *
	 * @covers \DmbcTools\SongListPlaylist::current_user_is_member
	 */
	public function test_current_user_is_member_matches_logged_in_member_roles(): void {
		$cases = array(
			'logged out user with member role' => array( false, array( 'um_member' ), false ),
			'logged in user with no roles'     => array( true, array(), false ),
			'ultimate member role'            => array( true, array( 'um_member' ), true ),
			'legacy member role'              => array( true, array( 'member' ), true ),
			'ordinary subscriber role'        => array( true, array( 'subscriber' ), false ),
			'mixed roles including member'    => array( true, array( 'subscriber', 'um_member' ), true ),
		);

		foreach ( $cases as $label => $case ) {
			$logged_in = $case[0];
			$roles     = $case[1];
			$expected  = $case[2];

			$GLOBALS['dmbc_test_state']['logged_in'] = $logged_in;
			$GLOBALS['dmbc_test_state']['current_user'] = new WP_User(
				$logged_in ? 15 : 0,
				array( 'roles' => $roles )
			);

			$this->assertSame( $expected, SongListPlaylist::current_user_is_member(), $label );
		}
	}

	public function test_get_or_update_playlist_stores_urls_in_song_list_order(): void {
		$library = $this->create_temp_directory();
		$this->make_directory_tree(
			$library,
			array(
				'Song B' => array(),
				'Song A' => array(),
			)
		);
		file_put_contents( $library . '/Song B/02-b.pdf', 'test' );
		file_put_contents( $library . '/Song A/01-a.pdf', 'test' );
		$this->set_option( Plugin::OPTION_SONGLIST_DIRECTORY, $library );

		$playlist = SongListPlaylist::get_or_update_playlist(
			55,
			array(
				array(
					'type'  => SongList::TYPE_SONG,
					'value' => 'Song B',
				),
				array(
					'type'  => SongList::TYPE_NOTE,
					'value' => 'Break',
				),
				array(
					'type'  => SongList::TYPE_SONG,
					'value' => 'Song A',
				),
			)
		);

		$expected = array(
			'http://example.test/wp-content/' . str_replace( array( 'C:', 'Song B' ), array( 'C%3A', 'Song%20B' ), ltrim( $library . '/Song B/02-b.pdf', '/' ) ),
			'http://example.test/wp-content/' . str_replace( array( 'C:', 'Song A' ), array( 'C%3A', 'Song%20A' ), ltrim( $library . '/Song A/01-a.pdf', '/' ) ),
		);

		$this->assertSame( $expected, $playlist );
		$this->assertSame( $expected, $this->get_stored_post_meta( 55, Plugin::PLAYLIST_META_KEY ) );
	}

	public function test_get_or_update_playlist_accepts_absolute_library_and_windows_style_song_paths(): void {
		$library = $this->create_temp_directory();
		$this->make_directory_tree(
			$library,
			array(
				'Song C' => array(
					'Learning Tracks' => array(),
				),
			)
		);
		file_put_contents( $library . '/Song C/Learning Tracks/tenor.mp3', 'test' );
		$this->set_option( Plugin::OPTION_SONGLIST_DIRECTORY, $library );

		$playlist = SongListPlaylist::get_or_update_playlist(
			66,
			array(
				array(
					'type'  => SongList::TYPE_SONG,
					'value' => 'Song C\\Learning Tracks',
				),
			)
		);

		$expected = array(
			'http://example.test/wp-content/' . str_replace( array( 'C:', 'Song C', 'Learning Tracks' ), array( 'C%3A', 'Song%20C', 'Learning%20Tracks' ), ltrim( $library . '/Song C/Learning Tracks/tenor.mp3', '/' ) ),
		);

		$this->assertSame( $expected, $playlist );
		$this->assertSame( $expected, $this->get_stored_post_meta( 66, Plugin::PLAYLIST_META_KEY ) );
	}
}
