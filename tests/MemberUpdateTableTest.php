<?php

require_once dirname( __DIR__ ) . '/src/member-update-table.php';

use DmbcTools\MemberUpdateTable;

/** @covers \DmbcTools\MemberUpdateTable */
final class MemberUpdateTableTest extends DmbcUnitTestBase {
	public function test_columns_and_date_renderer_return_expected_values(): void {
		$post             = new \WP_Post( 18 );
		$post->post_date  = '2026-09-04 10:30:00';
		$table            = new MemberUpdateTable();

		$this->assertSame(
			array(
				'title' => 'Title',
				'date'  => 'Published',
			),
			$table->get_columns()
		);
		$this->assertSame( '2026-09-04 10:30:00', $table->column_date( $post ) );
	}
}
