<?php

require_once dirname( __DIR__ ) . '/src/member-update-table.php';

use DmbcTools\MemberUpdateTable;
use DmbcTools\Plugin;

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
				'sent'  => 'Sent',
			),
			$table->get_columns()
		);
		$this->assertSame( '2026-09-04 10:30:00', $table->column_date( $post ) );
		$this->assertSame( '', $table->column_sent( $post ) );
		$this->set_post_meta( $post->ID, Plugin::MEMBER_UPDATE_SENT_META_KEY, '2026-09-04 11:00:00' );
		$this->assertSame( '2026-09-04 11:00:00', $table->column_sent( $post ) );
	}
}
