<?php
/**
 * DeluxeCcTransaction class file.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

use InvalidArgumentException;
use WP_User;
use DmbcTools\TransactionException;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * Handles Deluxe credit card transaction notifications.
 */
class DeluxeCcTransaction {
	/**
	 *  The transaction id field name.
	 *
	 * @var string
	 */
	public const string FIELD_TX_ID = 'TransactionId';
	/**
	 * The name of the database table (without the site's table prefix) that records notifications.
	 *
	 * @var string
	 */
	public const string NOTIFICATION_TABLE_NAME = 'dmbc_cc_notification';
	/**
	 * The name of the database table (without the site's table prefix) that records notifications.
	 *
	 * @var string
	 */
	public const string TICKET_TABLE_NAME = 'dmbc_ticket';

	/**
	 * Creates the database table that records Deluxe credit card transaction notifications.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = array();

		$table_name = $wpdb->prefix . self::NOTIFICATION_TABLE_NAME;
		$sql[]      = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_body LONGTEXT NOT NULL,
			received_at DATETIME NOT NULL,
			processing_result TEXT NOT NULL,
            transaction_id TEXT NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$table_name2 = $wpdb->prefix . self::TICKET_TABLE_NAME;
		$sql[]       = "CREATE TABLE {$table_name2} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			transaction_id TEXT NOT NULL,
			tx_date DATETIME NOT NULL,
            processed_at DATETIME NOT NULL,
            email TEXT,
			total decimal(10,2) NOT NULL,
            name TEXT NOT NULL,
            items TEXT NOT NULL,
			PRIMARY KEY  (id),
            UNIQUE KEY unique_transaction_id (transaction_id)
		) {$charset_collate};";

		if ( ! \function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		\dbDelta( $sql );
	}

	/**
	 * Handle a new transaction notification received from Deluxe.
	 *
	 * @param array $transaction The decoded JSON payload of the notification.
	 * @return void
	 * @throws TransactionException Catches everything and return nothing.
	 */
	public function handle_new_transaction( array $transaction ): void {
		// Process the Deluxe transaction payload.
		// 1. Find the existing user or create a new one based on the email address
		// 2. Add the user to the Donor Member Directory or set the Donor role?
		//
		// 2a. If the transaction is Status === APPROVED
		//
		// 3. If the transaction is a donation,
		// a. record it in Charitable?
		// b. Send the email to the user confirming the donation.
		//
		// 4. If the transaction is a show purchase, record the purchase details to the db.
		// 5. Send the email to the user.

		$user              = $this->get_or_create_user( $transaction );
		$processing_result = __( 'User retrieved or created successfully.', 'dmbc-tools' );
		$this->log_cc_notification( $transaction[ self::FIELD_TX_ID ], '', $processing_result );

		// Add the donor role to the user.
		$user->add_role( 'um_donor' );

		// We want to use the user info even if the transaction is not approved.
		if ( 'APPROVED' !== $transaction['Status'] ) {
			throw new TransactionException( 'Transaction not approved.' );
		}

		if ( isset( $transaction['SubmissionMethod'] ) && str_contains( $transaction['SubmissionMethod'], 'Spaghetti' ) ) {
			// Handle online submission method specific logic here.
			$this->handle_ticket_purchase( $transaction );
		} elseif ( isset( $transaction['SubmissionMethod'] ) && str_contains( $transaction['SubmissionMethod'], 'Donation' ) ) {
			// Handle other submission method specific logic here.
			$this->handle_donation( $transaction );
		} else {
			throw new TransactionException( 'Unknown submission method.' );
		}
	}

	/**
	 * Ticket handling workflow.
	 *
	 * @param array<string, mixed> $transaction The ticket purchase transaction payload.
	 * @return void
	 */
	public function handle_ticket_purchase( array $transaction ): void {

		$data = $this->record_tickets( $transaction );
		$this->send_ticket_purchase_email( $data );
	}

	/**
	 *
	 * Provides the interface for the mailer to the rest of the plugin.
	 *
	 * @param array $ticket_data The data related to the ticket purchase to be used in the email template.
	 * @return void
	 */
	public function send_ticket_purchase_email( array $ticket_data ): void {
		// TODO Add config setting for the email template name.
		Plugin::instance()->send_email_using_template( 'Ticket Purchase Confirmation Email', $ticket_data );
	}

	/**
	 *  Make a record of the ticket purchase transaction.
	 *
	 * @param array $transaction The data sent for the ticket purchase transaction.
	 * @throws TransactionException If there is an error recording the ticket purchase.
	 * @return array The recorded ticket purchase data.
	 */
	public function record_tickets( array $transaction ): array {
		global $wpdb;

		\error_log( 'Handling the ticket purchase. Transaction ID: ' . $transaction[ self::FIELD_TX_ID ] );

		// Record the ticket purchase (types of tickets, counts, update total?).
		$transaction_id = $transaction[ self::FIELD_TX_ID ];
		$amount         = $transaction['TransactionAmount'];
		$tx_date        = $transaction['DateTime'];
		$customer_name  = $transaction['Customer']['Name'] ?? '';
		$customer_email = $transaction['Customer']['EmailAddress'];

		$items = array();
		foreach ( $transaction['SaleItems'] as $ticket ) {
			$ticket_type  = $ticket['Name'] ?? '';
			$ticket_count = $ticket['Quantity'] ?? 0;
			$ticket_total = $ticket['Total'] ?? 0;

			$items[] = array(
				'type'  => $ticket_type,
				'count' => $ticket_count,
				'total' => $ticket_total,
			);
		}

		$data = array(
			'transaction_id' => $transaction_id,
			'name'           => $customer_name,
			'email'          => $customer_email,
			'tx_date'        => $tx_date,
			'processed_at'   => current_time( 'mysql' ),
			'items'          => \wp_json_encode( $items ),
			'total'          => $amount,
		);

		$result = $wpdb->insert(
			$wpdb->prefix . self::TICKET_TABLE_NAME,
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%f' )
		);

		if ( false === $result ) {

			// 3. Inspect the last database error for unique key constraints
			// MySQL unique constraint error codes usually contain "Duplicate entry"
			if ( strpos( $wpdb->last_error, 'Duplicate entry' ) !== false ) {
				// Handle the unique index error gracefully.
				throw new TransactionException( 'Insertion skipped: This transaction ID (' . esc_html( $transaction_id ) . ') has already been processed.' );

			}
			throw new TransactionException( 'Failed to insert transaction ID (' . esc_html( $transaction_id ) . ') into the database: ' . esc_html( $wpdb->last_error ) );
		}
		return $data;
	}

	/**
	 * Handle donation workflow
	 *
	 * @param array<string, mixed> $transaction The donation transaction payload.
	 * @return void
	 */
	public function handle_donation( array $transaction ): void {
		// TODO Implement the donation handling logic here.
	}

	/**
	 * Retrieves an existing user by email or creates a new one.
	 *
	 * @param array $transaction The decoded JSON payload of the notification.
	 * @return WP_User The existing or newly created user.
	 *
	 * @throws InvalidArgumentException If the user has no email defined.
	 */
	private function get_or_create_user( array $transaction ): WP_User {
		$customer = $transaction['Customer'];

		if ( ! $customer ) {
			throw new InvalidArgumentException( 'Malformed request - No customer data.' );
		}
		$user_email = $customer['EmailAddress'];

		if ( ! $user_email ) {
			throw new InvalidArgumentException( 'User email is required.' );
		}

		$user = \get_user_by( 'email', $user_email );

		if ( ! $user ) {
			$custom_fields = $transaction['CustomFields'] ?? array();
			$first_name    = $this->get_custom_field( $custom_fields, 'CustomerFirstName' );
			$last_name     = $this->get_custom_field( $custom_fields, 'CustomerLastName' );
			// Create a new user.
			$user_id = \wp_insert_user(
				array(
					'user_login'      => $user_email,
					'user_email'      => $user_email,
					'user_registered' => \current_time( 'mysql' ),
					'display_name'    => $customer['Name'],
					'first_name'      => $first_name,
					'last_name'       => $last_name,
					'user_pass'       => wp_generate_password(),
					'user_status'     => 'approved',
				)
			);
			if ( is_wp_error( $user_id ) ) {
				throw new InvalidArgumentException( 'Failed to create user:  ' . esc_html( $user_id->get_error_message() ) );
			}

			$this->set_acf_address( $user_id, $customer );

			\clean_user_cache( $user_id );
			$user = \get_user_by( 'id', $user_id );
		}
		return $user;
	}

	/**
	 * Set the user address for the given user ID based on the customer data. This routine uses
	 * the field keys instead of the field label so it will always work for the user.
	 *
	 * @param int   $user_id  The ID of the user.
	 * @param array $customer The customer data containing the address information.
	 * @return void
	 */
	private function set_acf_address( int $user_id, array $customer ): void {

		$user_key = 'user_' . $user_id;
		foreach ( array(
			'field_69a4b16aa4a13' => $customer['Address'] ?? '',
			'field_69a4b1a6a4a14' => $customer['City'] ?? '',
			'field_69a4b1bba4a15' => $customer['State'] ?? '',
			'field_69a4b1caa4a16' => $customer['PostalCode'] ?? '',
			'field_6aa438ce4ed00' => $customer['Phone'] ?? '',
		) as $name => $value ) {
			\update_field( $name, $value, $user_key );
		}
	}

	/**
	 * Get the custom field value for the given field name
	 *
	 * @param array  $custom_fields The array of customer fields(Name/Value pairs) from the transaction.
	 * @param string $field_name The name of the custom field to retrieve.
	 * @return string|null The value of the custom field, or null if not found.
	 */
	public function get_custom_field( array $custom_fields, string $field_name ): ?string {
		foreach ( $custom_fields as $custom_field ) {
			if ( isset( $custom_field['Name'] ) && $custom_field['Name'] === $field_name ) {
				return $custom_field['Value'];
			}
		}
		return null;
	}

	/**
	 * Passes the notification's decoded JSON body to handle_new_transaction().
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_deluxe_cc_notification( \WP_REST_Request $request ): \WP_REST_Response {
		\error_log( 'Handling Deluxe CC notification' );
		try {
			$body           = (string) $request->get_body();
			$transaction    = $request->get_json_params();
			$transaction_id = $transaction[ self::FIELD_TX_ID ];
			$this->log_cc_notification( $transaction_id, $body, 'starting' );

			\error_log( 'processing transaction: ' . $transaction_id );
			$this->handle_new_transaction( $transaction );

			$this->log_cc_notification( $transaction[ self::FIELD_TX_ID ], '', 'complete' );
			return new \WP_REST_Response( null, 204 );

		} catch ( TransactionException $e ) {
			$processing_result = 'Transaction Error: ' . $e->getMessage();
			$this->log_cc_notification( $transaction[ self::FIELD_TX_ID ], '', $processing_result );
			return new \WP_REST_Response( array( 'error' => $e->getMessage() ), 422 );

		} catch ( \Throwable $e ) {
			$this->log_cc_notification( $transaction[ self::FIELD_TX_ID ], '', 'error: ' . $e->getMessage() );
			return new \WP_REST_Response( array( 'error' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Records a Deluxe credit card notification and its processing result.
	 *
	 * @param string $transaction_id     The ID of the transaction being recorded, if record already exists.
	 * @param string $post_body         The raw JSON body of the notification.
	 * @param string $processing_result The result of processing the notification.
	 * @return void
	 */
	private function log_cc_notification( string $transaction_id, string $post_body, string $processing_result ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::NOTIFICATION_TABLE_NAME,
			array(
				'transaction_id'    => $transaction_id,
				'post_body'         => $post_body,
				'received_at'       => \current_time( 'mysql' ),
				'processing_result' => $processing_result,
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}
}
