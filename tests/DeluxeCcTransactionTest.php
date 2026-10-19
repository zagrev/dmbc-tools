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

		$dbdelta_calls = $GLOBALS['dmbc_test_state']['dbdelta_calls'];
		$this->assertCount( 1, $dbdelta_calls );

		$schemas = $dbdelta_calls[0];
		$this->assertCount( 3, $schemas );
		$this->assertStringContainsString( 'wp_' . DeluxeCcTransaction::NOTIFICATION_TABLE_NAME, $schemas[0] );
		$this->assertStringContainsString( 'wp_' . DeluxeCcTransaction::TICKET_TABLE_NAME, $schemas[2] );

		$donation_schema = $schemas[1];
		$this->assertStringContainsString( 'wp_' . DeluxeCcTransaction::DONATION_TABLE_NAME, $donation_schema );
		$this->assertStringContainsString( 'transaction_id varchar(191) NOT NULL', $donation_schema );
		$this->assertStringContainsString( 'tx_date DATETIME NOT NULL', $donation_schema );
		$this->assertStringContainsString( 'email TEXT', $donation_schema );
		$this->assertStringContainsString( 'total decimal(10,2) NOT NULL', $donation_schema );
		$this->assertStringContainsString( 'items TEXT NOT NULL', $donation_schema );
		$this->assertStringContainsString( 'UNIQUE KEY unique_transaction_id (transaction_id)', $donation_schema );
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
		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['users'] );
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

	public function test_approved_donation_is_recorded_and_confirmation_is_sent(): void {
		$payload = $this->make_valid_transaction();
		$payload['SubmissionMethod'] = 'Online Donation';
		unset( $payload['SaleItems'] );
		$template = new WP_Post( 50 );
		$template->post_title = 'Donation Confirmation Email';
		$template->post_content = 'Thanks {{CustomerName}} for {{PurchaseAmount}} on {{PurchaseDate}}. {{ItemList}}{{ItemName}}: {{ItemQuantity}} / {{ItemPrice}}{{/ItemList}}';
		$GLOBALS['dmbc_test_state']['posts'][50] = $template;

		$response = ( new DeluxeCcTransaction() )->handle_deluxe_cc_notification( new WP_REST_Request( $payload, wp_json_encode( $payload ) ) );

		$this->assertSame( 204, $response->get_status() );
		$donations = array_values( array_filter( $this->get_wpdb_inserts(), static fn( $insert ) => 'wp_' . DeluxeCcTransaction::DONATION_TABLE_NAME === $insert['table'] ) );
		$this->assertCount( 1, $donations );
		$data = $donations[0]['data'];
		$this->assertSame( 'abc123', $data['transaction_id'] );
		$this->assertSame( 'customer@example.com', $data['email'] );
		$this->assertSame( 42.50, $data['total'] );
		$this->assertSame( array( array( 'type' => 'Donation', 'count' => 1, 'total' => 42.50 ) ), json_decode( $data['items'], true ) );
		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['users'] );
		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['mail_calls'] );
		$mail = $GLOBALS['dmbc_test_state']['mail_calls'][0];
		$this->assertSame( 'Donation Confirmation Email', $mail['subject'] );
		$this->assertSame( 'customer@example.com', $mail['recipients'] );
		$this->assertStringContainsString( 'Thanks Test Customer for 42.5', $mail['message'] );
		$this->assertStringContainsString( 'Donation: 1 / 42.5', $mail['message'] );
	}

	public function test_portal_payment_donation_creates_user_when_email_is_present(): void {
		$payload = $this->make_valid_transaction();
		$payload['SubmissionMethod'] = 'Portal-Payments';

		( new DeluxeCcTransaction() )->handle_new_transaction( $payload );

		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['users'] );
		$donations = array_values( array_filter( $this->get_wpdb_inserts(), static fn( $insert ) => 'wp_' . DeluxeCcTransaction::DONATION_TABLE_NAME === $insert['table'] ) );
		$this->assertCount( 1, $donations );
	}

	public function test_donation_without_email_is_recorded_without_creating_user(): void {
		$payload = $this->make_valid_transaction();
		$payload['SubmissionMethod'] = 'Online Donation';
		unset( $payload['Customer']['EmailAddress'] );

		( new DeluxeCcTransaction() )->handle_new_transaction( $payload );

		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['users'] );
		$donations = array_values( array_filter( $this->get_wpdb_inserts(), static fn( $insert ) => 'wp_' . DeluxeCcTransaction::DONATION_TABLE_NAME === $insert['table'] ) );
		$this->assertCount( 1, $donations );
		$this->assertSame( '', $donations[0]['data']['email'] );
	}

	public function test_donation_preserves_sale_items(): void {
		$payload = $this->make_valid_transaction();
		$payload['SaleItems'][0]['Name'] = 'General Fund';
		( new DeluxeCcTransaction() )->handle_donation( $payload );
		$data = $this->get_wpdb_inserts()[0]['data'];
		$this->assertSame( 'General Fund', json_decode( $data['items'], true )[0]['type'] );
	}

	public static function invalid_donations(): array {
		return array(
			array( 'Status', 'DECLINED' ),
			array( 'TransactionId', '' ),
			array( 'TransactionAmount', 0 ),
			array( 'TransactionAmount', -1 ),
			array( 'TransactionAmount', 'invalid' ),
			array( 'TransactionAmount', 100000000 ),
			array( 'DateTime', 'invalid' ),
			array( 'DateTime', '2026-02-30 12:00:00' ),
			array( 'DateTime', 'next monday' ),
			array( 'SaleItems', 'invalid' ),
			array( 'SaleItems', array( 'invalid' ) ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalid_donations' )]
	public function test_invalid_donations_are_not_recorded_or_emailed( string $field, mixed $value ): void {
		$payload = $this->make_valid_transaction();
		$payload[ $field ] = $value;
		try {
			( new DeluxeCcTransaction() )->handle_donation( $payload );
			$this->fail( 'Invalid donations must be rejected.' );
		} catch ( \DmbcTools\TransactionException $exception ) {
			$this->assertNotEmpty( $exception->getMessage() );
		}
		$this->assertSame( array(), $this->get_wpdb_inserts() );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['mail_calls'] );
	}

	public function test_duplicate_donation_returns_error_without_sending_another_email(): void {
		$payload = $this->make_valid_transaction();
		$payload['SubmissionMethod'] = 'Donation';
		$template = new WP_Post( 50 );
		$template->post_title = 'Donation Confirmation Email';
		$template->post_content = 'Thank you {{CustomerName}}';
		$GLOBALS['dmbc_test_state']['posts'][50] = $template;
		$GLOBALS['dmbc_test_state']['wpdb_insert_errors']['wp_' . DeluxeCcTransaction::DONATION_TABLE_NAME] = 'Duplicate entry abc123';
		$response = ( new DeluxeCcTransaction() )->handle_deluxe_cc_notification( new WP_REST_Request( $payload, wp_json_encode( $payload ) ) );
		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['mail_calls'] );
	}

	public function test_failed_donation_insert_throws_a_transaction_error(): void {
		$GLOBALS['dmbc_test_state']['wpdb_insert_errors']['wp_' . DeluxeCcTransaction::DONATION_TABLE_NAME] = 'Database unavailable';
		$this->expectException( \DmbcTools\TransactionException::class );
		$this->expectExceptionMessage( 'Database unavailable' );
		( new DeluxeCcTransaction() )->handle_donation( $this->make_valid_transaction() );
	}

	public function test_donation_normalizes_iso_dates_and_amounts_for_storage(): void {
		$payload = $this->make_valid_transaction();
		$payload['DateTime'] = '2026-10-12T12:00:00Z';
		$payload['TransactionAmount'] = '42.505';
		( new DeluxeCcTransaction() )->handle_donation( $payload );
		$data = $this->get_wpdb_inserts()[0]['data'];
		$this->assertSame( '2026-10-12 12:00:00', $data['tx_date'] );
		$this->assertSame( 42.51, $data['total'] );
	}

	public function test_declined_donation_notification_does_not_record_a_donation(): void {
		$payload = $this->make_valid_transaction();
		$payload['SubmissionMethod'] = 'Donation';
		$payload['Status'] = 'DECLINED';
		$response = ( new DeluxeCcTransaction() )->handle_deluxe_cc_notification( new WP_REST_Request( $payload, wp_json_encode( $payload ) ) );
		$this->assertSame( 422, $response->get_status() );
		$donations = array_filter( $this->get_wpdb_inserts(), static fn( $insert ) => 'wp_' . DeluxeCcTransaction::DONATION_TABLE_NAME === $insert['table'] );
		$this->assertSame( array(), $donations );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['mail_calls'] );
	}
}
