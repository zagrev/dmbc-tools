<?php

require_once dirname( __DIR__ ) . '/src/ticket-table.php';

use DmbcTools\DeluxeCcTransaction;
use DmbcTools\TicketTable;

/** @covers \DmbcTools\TicketTable */
final class TicketTableTest extends DmbcUnitTestBase {
	public function test_columns_and_default_renderers_return_expected_values(): void {
		$table = new TicketTable();

		$this->assertSame(
			array(
				'tx_date'        => 'Date',
				'name'           => 'Customer Name',
				'email'          => 'Email',
				'item_count'     => 'Items',
				'items_by_type'  => 'Items by Type',
				'total'          => 'Total',
				'transaction_id' => 'Transaction ID',
			),
			$table->get_columns()
		);

		$row = array(
			'transaction_id' => 'tx-200',
			'name'           => 'Buyer Name',
			'email'          => 'buyer@example.com',
			'item_count'     => 4,
			'items_by_type'  => 'Adult: 4',
			'total'          => 80,
			'tx_date'        => '2026-09-18',
		);

		$this->assertSame( 'tx-200', $table->column_default( $row, 'transaction_id' ) );
		$this->assertSame( 'Buyer Name', $table->column_default( $row, 'name' ) );
		$this->assertSame( 'buyer@example.com', $table->column_default( $row, 'email' ) );
		$this->assertSame( '4', $table->column_default( $row, 'item_count' ) );
		$this->assertSame( 'Adult: 4', $table->column_default( $row, 'items_by_type' ) );
		$this->assertSame( '$80.00', $table->column_default( $row, 'total' ) );
		$this->assertSame( '2026-09-18', $table->column_default( $row, 'tx_date' ) );
		$this->assertSame( '', $table->column_default( $row, 'unknown' ) );
	}

	public function test_prepare_items_builds_rows_totals_and_grouped_item_counts(): void {
		$GLOBALS['dmbc_test_state']['wpdb_results'] = array(
			array(
				'transaction_id' => 'tx-300',
				'name'           => 'First Buyer',
				'email'          => 'first@example.com',
				'items'          => wp_json_encode(
					array(
						array(
							'type'  => 'Adult',
							'count' => 2,
						),
						array(
							'type'  => 'Adult',
							'count' => 1,
						),
						array(
							'type'  => 'Student',
							'count' => 3,
						),
					)
				),
				'total'          => '100.25',
				'tx_date'        => '2026-09-18 10:00:00',
			),
			array(
				'transaction_id' => 'tx-301',
				'items'          => 'not-json',
				'total'          => '5.75',
			),
		);

		$table = new TicketTable();
		$table->prepare_items();

		$this->assertCount( 2, $table->get_items() );
		$this->assertSame( 6, $table->get_grand_item_count() );
		$this->assertSame( 106.0, $table->get_grand_total() );
		$this->assertSame( 'Adult: 3, Student: 3', $table->get_items()[0]['items_by_type'] );
		$this->assertSame( 0, $table->get_items()[1]['item_count'] );
		$this->assertSame( '', $table->get_items()[1]['items_by_type'] );
		$this->assertNotEmpty( $GLOBALS['dmbc_test_state']['cache']['dmbc_ticket_table']['dmbc_ticket_table_rows_wp_' . DeluxeCcTransaction::TICKET_TABLE_NAME] );
	}

	public function test_prepare_items_uses_cached_rows_when_available(): void {
		$cache_key = 'dmbc_ticket_table_rows_wp_' . DeluxeCcTransaction::TICKET_TABLE_NAME;
		$GLOBALS['dmbc_test_state']['cache']['dmbc_ticket_table'][ $cache_key ] = array(
			array(
				'transaction_id' => 'cached-tx',
				'items'          => wp_json_encode( array( array( 'type' => 'Adult', 'count' => 1 ) ) ),
				'total'          => '10',
			),
		);
		$GLOBALS['dmbc_test_state']['wpdb_results'] = array(
			array(
				'transaction_id' => 'db-tx',
				'items'          => '[]',
				'total'          => '1',
			),
		);

		$table = new TicketTable();
		$table->prepare_items();

		$this->assertSame( 'cached-tx', $table->get_items()[0]['transaction_id'] );
		$this->assertSame( 1, $table->get_grand_item_count() );
		$this->assertSame( 10.0, $table->get_grand_total() );
	}
}
