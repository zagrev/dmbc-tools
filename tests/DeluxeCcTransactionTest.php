<?php

use DmbcTools\DeluxeCcTransaction;

/** @covers \DmbcTools\DeluxeCcTransaction */
final class DeluxeCcTransactionTest extends DmbcUnitTestBase {

	private function make_valid_transaction(): array {
		return array(
			DeluxeCcTransaction::FIELD_TX_ID => 'abc123',
			'Status'                        => 'APPROVED',
			'SubmissionMethod'              => 'Spaghetti',
			'TransactionAmount'             => 42.50,
			'DateTime'                      => '2026-09-02 12:00:00',
			'Customer'                      => array(
				'Name'         => 'Test Customer',
				'EmailAddress' => 'customer@example.com',
			),
			'SaleItems'                     => array(
				array(
					'Name'     => 'Adult Ticket',
					'Quantity' => 1,
					'Total'    => 42.50,
				),
			),
			'CustomFields'                  => array(),
		);
	}

	/**
	 * Method create_table() creates the notification table via dbDelta().
	 *
	 * @covers \DmbcTools\DeluxeCcTransaction::create_table
	 */
	public function test_create_table_runs_dbdelta(): void {
		DeluxeCcTransaction::create_table();

		$calls = $GLOBALS['dmbc_test_state']['dbdelta_calls'] ? $GLOBALS['dmbc_test_state']['dbdelta_calls'][0] : array() ;
		$this->assertNotEmpty( $calls );
		$this->assertSame(2, count( $calls ) );
		$this->assertStringContainsString( 'wp_' . DeluxeCcTransaction::TICKET_TABLE_NAME, array_pop( $calls ) );
		$this->assertStringContainsString( 'wp_' . DeluxeCcTransaction::NOTIFICATION_TABLE_NAME, array_pop( $calls ) );
	}

	/**
	 * Method handle_new_transaction() records the notification and ticket purchase data for a valid approved transaction.
	 *
	 * @covers \DmbcTools\DeluxeCcTransaction::handle_new_transaction
	 */
	public function test_handle_new_transaction_records_successful_notification(): void {
		( new DeluxeCcTransaction() )->handle_new_transaction( $this->make_valid_transaction() );

		$inserts = $this->get_wpdb_inserts();
		$this->assertNotEmpty( $inserts );
		$tables = array_map( fn( $insert ) => $insert['table'], $inserts );
		$this->assertContains( 'wp_' . DeluxeCcTransaction::NOTIFICATION_TABLE_NAME, $tables );
		$this->assertContains( 'wp_' . DeluxeCcTransaction::TICKET_TABLE_NAME, $tables );
	}

	/**
	 * Method handle_deluxe_cc_notification() forwards the request's JSON body to handle_new_transaction().
	 *
	 * @covers \DmbcTools\DeluxeCcTransaction::handle_deluxe_cc_notification
	 */
	public function test_handle_deluxe_cc_notification_forwards_json_body_and_returns_no_content(): void {
		$payload = $this->make_valid_transaction();
		$request = new \WP_REST_Request( $payload, wp_json_encode( $payload ) );

		$response = ( new DeluxeCcTransaction() )->handle_deluxe_cc_notification( $request );

		$this->assertSame( 204, $response->get_status() );
	}

	/**
	 * Method handle_deluxe_cc_notification() records the raw body and result of a successful notification.
	 *
	 * @covers \DmbcTools\DeluxeCcTransaction::handle_deluxe_cc_notification
	 */
	public function test_handle_deluxe_cc_notification_records_successful_notification(): void {
		$payload = $this->make_valid_transaction();
		$request = new \WP_REST_Request( $payload, wp_json_encode( $payload ) );

		( new DeluxeCcTransaction() )->handle_deluxe_cc_notification( $request );

		$inserts = $this->get_wpdb_inserts();
		$this->assertNotEmpty( $inserts );
		$notification_inserts = array_values(
			array_filter(
				$inserts,
				fn( $insert ) => 'wp_' . DeluxeCcTransaction::NOTIFICATION_TABLE_NAME === $insert['table']
			)
		);
		$this->assertNotEmpty( $notification_inserts );
		$notification_insert = end( $notification_inserts );
		$this->assertSame( wp_json_encode( $payload ), $notification_insert['data']['post_body'] );
		$this->assertSame( 'complete', $notification_insert['data']['processing_result'] );
		$this->assertNotEmpty( $notification_insert['data']['received_at'] );
	}
}
