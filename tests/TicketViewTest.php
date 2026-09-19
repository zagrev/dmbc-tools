<?php

require_once dirname( __DIR__ ) . '/src/ticket-table.php';
require_once dirname( __DIR__ ) . '/src/ticket-view.php';

use DmbcTools\TicketView;

/** @covers \DmbcTools\TicketView */
final class TicketViewTest extends DmbcUnitTestBase {
	public function test_ticket_table_page_renders_totals_and_rows(): void {
		$GLOBALS['dmbc_test_state']['wpdb_results'] = array(
			array(
				'transaction_id' => 'tx-100',
				'name'           => 'Test Buyer',
				'email'          => 'buyer@example.com',
				'items'          => wp_json_encode(
					array(
						array(
							'type'  => 'Adult',
							'count' => 2,
						),
						array(
							'type'  => 'Student',
							'count' => 1,
						),
					)
				),
				'total'          => '45.50',
				'tx_date'        => '2026-09-18 12:00:00',
			),
		);

		ob_start();
		( new TicketView() )->render_ticket_table_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Print tickets', $html );
		$this->assertStringContainsString( 'Ticket Purchases', $html );
		$this->assertStringContainsString( 'Total Items:', $html );
		$this->assertStringContainsString( '3', $html );
		$this->assertStringContainsString( '$45.50', $html );
		$this->assertStringContainsString( 'Test Buyer', $html );
		$this->assertStringContainsString( 'Adult: 2, Student: 1', $html );
	}
}
