<?php

use DmbcTools\SongList;

/** @covers \DmbcTools\SongList */
final class SongListTest extends DmbcUnitTestBase {
	public function test_constructor_and_getters_return_supplied_values(): void {
		$date  = new \DateTimeImmutable( '2026-09-02' );
		$songs = array( 'Song A', 'Song B' );
		$list  = new SongList( 'September rehearsal', $songs, $date, 'Bring folders.' );

		$this->assertSame( 'September rehearsal', $list->get_name() );
		$this->assertSame( $songs, $list->get_songs() );
		$this->assertSame( $date, $list->get_rehearsal_date() );
		$this->assertSame( 'Bring folders.', $list->get_note() );
	}

	public function test_setters_update_all_values(): void {
		$list = new SongList( 'Original', array(), new \DateTimeImmutable( '2026-01-01' ), 'Original note' );
		$date = new \DateTimeImmutable( '2026-09-09' );
		$list->set_name( 'Updated' );
		$list->set_songs( array( 'Song C' ) );
		$list->set_rehearsal_date( $date );
		$list->set_note( 'Updated note' );

		$this->assertSame( 'Updated', $list->get_name() );
		$this->assertSame( array( 'Song C' ), $list->get_songs() );
		$this->assertSame( $date, $list->get_rehearsal_date() );
		$this->assertSame( 'Updated note', $list->get_note() );
	}
}
