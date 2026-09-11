<?php

use DmbcTools\DeluxeCcTransaction;

/** @covers \DmbcTools\DeluxeCcTransaction */
final class DeluxeCcTransactionTest extends DmbcUnitTestBase {

	/**
	 * Method create_table() creates the notification table via dbDelta().
	 *
	 * @covers \DmbcTools\DeluxeCcTransaction::create_table
	 */
	public function test_create_table_runs_dbdelta(): void {
		DeluxeCcTransaction::create_table();

		$calls = $GLOBALS['dmbc_test_state']['dbdelta_calls'];
		$this->assertNotEmpty( $calls );
		$this->assertStringContainsString( 'wp_' . DeluxeCcTransaction::TABLE_NAME, end( $calls ) );
	}

	/**
	 * Method handle_new_transaction() records the raw body and result of a successful notification.
	 *
	 * @covers \DmbcTools\DeluxeCcTransaction::handle_new_transaction
	 */
	public function test_handle_new_transaction_records_successful_notification(): void {
		( new DeluxeCcTransaction() )->handle_new_transaction( array( 'transaction_id' => 'abc123' ) );

		$inserts = $this->get_wpdb_inserts();
		$this->assertCount( 1, $inserts );
		$this->assertSame( 'wp_' . DeluxeCcTransaction::TABLE_NAME, $inserts[0]['table'] );
		$this->assertSame( '{"transaction_id":"abc123"}', $inserts[0]['data']['post_body'] );
		$this->assertSame( 'success', $inserts[0]['data']['processing_result'] );
		$this->assertNotEmpty( $inserts[0]['data']['received_at'] );
	}

	/**
	 * Method handle_deluxe_cc_notification() forwards the request's JSON body to handle_new_transaction().
	 *
	 * @covers \DmbcTools\DeluxeCcTransaction::handle_deluxe_cc_notification
	 */
	public function test_handle_deluxe_cc_notification_forwards_json_body_and_returns_no_content(): void {
		$request  = new \WP_REST_Request( array( 'transaction_id' => 'abc123' ), '{"transaction_id":"abc123"}' );
		$response = ( new DeluxeCcTransaction() )->handle_deluxe_cc_notification( $request );

		$this->assertSame( 204, $response->get_status() );
	}

	/**
	 * Method handle_deluxe_cc_notification() records the raw body and result of a successful notification.
	 *
	 * @covers \DmbcTools\DeluxeCcTransaction::handle_deluxe_cc_notification
	 */
	public function test_handle_deluxe_cc_notification_records_successful_notification(): void {
		$request = new \WP_REST_Request( array( 'transaction_id' => 'abc123' ), '{"transaction_id":"abc123"}' );

		( new DeluxeCcTransaction() )->handle_deluxe_cc_notification( $request );

		$inserts = $this->get_wpdb_inserts();
		$this->assertCount( 1, $inserts );
		$this->assertSame( 'wp_' . DeluxeCcTransaction::TABLE_NAME, $inserts[0]['table'] );
		$this->assertSame( '{"transaction_id":"abc123"}', $inserts[0]['data']['post_body'] );
		$this->assertSame( 'success', $inserts[0]['data']['processing_result'] );
		$this->assertNotEmpty( $inserts[0]['data']['received_at'] );
	}
}
