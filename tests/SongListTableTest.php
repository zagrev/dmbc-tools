<?php

require_once dirname( __DIR__ ) . '/src/songlist-table.php';

use DmbcTools\Plugin;
use DmbcTools\SongListTable;

/** @covers \DmbcTools\SongListTable */
final class SongListTableTest extends DmbcUnitTestBase {
	private function make_post( int $id = 12 ): \WP_Post {
		$post                                = new \WP_Post( $id );
		$post->post_title                    = 'September rehearsal';
		$post->dmbc_song_list_rehearsal_date = '2026-09-02';
		return $post;
	}

	public function test_constructor_columns_and_sortable_columns(): void {
		$table = new SongListTable();
		$this->assertSame( 'Rehearsal Date', $table->get_columns()['rehearsal_date'] );
		$this->assertSame( array( 'name', false ), $table->get_sortable_columns()['name'] );
	}

	public function test_column_renderers_return_post_values(): void {
		$table = new SongListTable();
		$post  = $this->make_post();
		$this->set_post_meta( $post->ID, Plugin::SONGS_META_KEY, array( 'Song A', 'Song B' ) );
		$this->set_post_meta( $post->ID, Plugin::PERFORMANCE_DATE_META_KEY, '2026-09-09' );

		$this->assertSame( 'September rehearsal', $table->column_name( $post ) );
		$this->assertSame( '<input type="checkbox" name="bulk-items[]" value="12" />', $table->column_cb( $post ) );
		$this->assertSame( 'Song A, Song B', $table->column_songs( $post ) );
		$this->assertStringContainsString( '2026-09-09', $table->column_rehearsal_date( $post ) );
	}

	public function test_column_rehearsal_date_includes_actions_for_a_valid_nonce(): void {
		$table    = new SongListTable();
		$_REQUEST = array(
			'_wpnonce' => 'nonce',
			'page'     => 'dmbc-songlist-edit',
		);
		$output   = $table->column_rehearsal_date( $this->make_post() );

		$this->assertStringContainsString( 'song_list_id=12', $output );
	}

	public function test_prepare_items_queries_published_song_lists(): void {
		$table                               = new SongListTable();
		$post                                = $this->make_post();
		$GLOBALS['dmbc_test_state']['posts'] = array( $post );

		$table->prepare_items();

		$this->assertSame( array( $post ), $table->get_items() );
		$this->assertSame( Plugin::SONGLIST_POST_TYPE, $GLOBALS['dmbc_test_state']['last_get_posts_args']['post_type'] );
	}
}
