<?php
/**
 * Donation table for the WordPress admin.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays approved donations and their total.
 */
class DonationTable extends \WP_List_Table {
	/**
	 * Total donation amount in cents.
	 *
	 * @var int
	 */
	private int $total_cents = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Donation', 'dmbc-tools' ),
				'plural'   => __( 'Donations', 'dmbc-tools' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get the donation columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'tx_date'        => __( 'Date', 'dmbc-tools' ),
			'name'           => __( 'Donor Name', 'dmbc-tools' ),
			'email'          => __( 'Email', 'dmbc-tools' ),
			'total'          => __( 'Amount', 'dmbc-tools' ),
			'transaction_id' => __( 'Transaction ID', 'dmbc-tools' ),
		);
	}

	/**
	 * Load donations, newest first.
	 *
	 * @param \DateTimeImmutable|null $start_date First included date.
	 * @param \DateTimeImmutable|null $end_date Last included date.
	 * @return void
	 */
	public function prepare_items( ?\DateTimeImmutable $start_date = null, ?\DateTimeImmutable $end_date = null ): void {
		global $wpdb;

		$end_date   = $end_date ?? new \DateTimeImmutable( \current_time( 'Y-m-d' ) );
		$start_date = $start_date ?? $end_date->modify( '-29 days' );

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$rows                  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE tx_date >= %s AND tx_date < %s ORDER BY tx_date DESC, id DESC',
				$wpdb->prefix . DeluxeCcTransaction::DONATION_TABLE_NAME,
				$start_date->format( 'Y-m-d' ) . ' 00:00:00',
				$end_date->modify( '+1 day' )->format( 'Y-m-d' ) . ' 00:00:00'
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			Plugin::instance()->logger()->error( 'Unable to load donations: ' . $wpdb->last_error );
			\wp_die( \esc_html__( 'Unable to load donations.', 'dmbc-tools' ) );
		}

		$this->items       = $rows;
		$this->total_cents = 0;
		foreach ( $rows as $row ) {
			$this->total_cents += (int) round( (float) $row['total'] * 100 );
		}
	}

	/**
	 * Render an escaped donation cell.
	 *
	 * @param array  $item The donation.
	 * @param string $column_name The column.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		if ( 'total' === $column_name ) {
			return \esc_html( '$' . number_format( (float) $item['total'], 2 ) );
		}
		return isset( $this->get_columns()[ $column_name ] ) ? \esc_html( (string) ( $item[ $column_name ] ?? '' ) ) : '';
	}

	/**
	 * Get the total across the selected donations.
	 *
	 * @return float
	 */
	public function get_grand_total(): float {
		return $this->total_cents / 100;
	}

	/**
	 * Get the number of donations.
	 *
	 * @return int
	 */
	public function get_donation_count(): int {
		return count( $this->items );
	}
}
