<?php

use DmbcTools\SongList;

/** @covers \DmbcTools\SongList */
final class SongListTest extends DmbcUnitTestBase {
	public function test_constructor_and_getters_return_supplied_values(): void {
		$date  = new \DateTimeImmutable( '2026-09-02' );
		$items = array(
			array(
				'type'  => SongList::TYPE_SONG,
				'value' => 'Song A',
			),
			array(
				'type'  => SongList::TYPE_NOTE,
				'value' => 'Ten-minute break',
			),
		);
		$list  = new SongList( 'September rehearsal', $items, $date, 'Bring folders.' );

		$this->assertSame( 'September rehearsal', $list->get_name() );
		$this->assertSame( $items, $list->get_items() );
		$this->assertSame( $date, $list->get_rehearsal_date() );
		$this->assertSame( 'Bring folders.', $list->get_note() );
	}

	public function test_setters_update_all_values(): void {
		$list = new SongList( 'Original', array(), new \DateTimeImmutable( '2026-01-01' ), 'Original note' );
		$date = new \DateTimeImmutable( '2026-09-09' );
		$list->set_name( 'Updated' );
		$list->set_items( array( array( 'type' => SongList::TYPE_SONG, 'value' => 'Song C' ) ) );
		$list->set_rehearsal_date( $date );
		$list->set_note( 'Updated note' );

		$this->assertSame( 'Updated', $list->get_name() );
		$this->assertSame( array( array( 'type' => SongList::TYPE_SONG, 'value' => 'Song C' ) ), $list->get_items() );
		$this->assertSame( $date, $list->get_rehearsal_date() );
		$this->assertSame( 'Updated note', $list->get_note() );
	}

	public function test_legacy_string_items_are_normalized_to_songs(): void {
		$list = new SongList( 'Legacy', array( 'Song A', 'Song B' ), new \DateTimeImmutable( '2026-01-01' ), '' );

		$this->assertSame(
			array(
				array(
					'type'  => SongList::TYPE_SONG,
					'value' => 'Song A',
				),
				array(
					'type'  => SongList::TYPE_SONG,
					'value' => 'Song B',
				),
			),
			$list->get_items()
		);
	}

	public function test_normalize_items_handles_strings_and_invalid_entries(): void {
		$this->assertSame(
			array(
				array(
					'type'  => SongList::TYPE_SONG,
					'value' => 'Song A',
				),
				array(
					'type'  => SongList::TYPE_NOTE,
					'value' => 'Break',
				),
			),
			SongList::normalize_items(
				array(
					'Song A',
					array(
						'type'  => 'note',
						'value' => 'Break',
					),
					'',
					array( 'type' => 'bogus' ),
					array(
						'type'  => 'bogus',
						'value' => '  ',
					),
				)
			)
		);

		$this->assertSame(
			array(
				array(
					'type'  => SongList::TYPE_SONG,
					'value' => 'Song A',
				),
				array(
					'type'  => SongList::TYPE_SONG,
					'value' => 'Song B',
				),
			),
			SongList::normalize_items( "Song A\nSong B" )
		);

		$this->assertSame( array(), SongList::normalize_items( null ) );
	}
}
