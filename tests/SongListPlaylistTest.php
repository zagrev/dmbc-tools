<?php

use DmbcTools\Plugin;
use DmbcTools\SongList;
use DmbcTools\SongListPlaylist;

/** @covers \DmbcTools\SongListPlaylist */
final class SongListPlaylistTest extends DmbcUnitTestBase {
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
}
