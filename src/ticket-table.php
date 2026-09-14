<?php
/**
 * TicketTable class file.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * Shows Deluxe ticket transactions in the WordPress admin.
 */
class TicketTable extends \WP_List_Table {
	/**
	 * Running total across all displayed ticket rows.
	 *
	 * @var float
	 */
	private float $grand_total = 0.0;

	/**
	 * Running item count across all displayed ticket rows.
	 *
	 * @var int
	 */
	private int $grand_item_count = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Ticket', 'dmbc-tools' ),
				'plural'   => __( 'Tickets', 'dmbc-tools' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Retrieve the columns for the table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'transaction_id' => __( 'Transaction ID', 'dmbc-tools' ),
			'name'           => __( 'Customer Name', 'dmbc-tools' ),
			'email'          => __( 'Email', 'dmbc-tools' ),
			'item_count'     => __( 'Items', 'dmbc-tools' ),
			'items_by_type'  => __( 'Items by Type', 'dmbc-tools' ),
			'total'          => __( 'Total', 'dmbc-tools' ),
			'tx_date'        => __( 'Date', 'dmbc-tools' ),
		);
	}

	/**
	 * Prepare and gather the ticket rows.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		global $wpdb;

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$table_name            = $wpdb->prefix . DeluxeCcTransaction::TICKET_TABLE_NAME;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows                   = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY tx_date DESC, id DESC", ARRAY_A );
		$this->items            = array();
		$this->grand_total      = 0.0;
		$this->grand_item_count = 0;

		foreach ( $rows as $row ) {
			$items_json    = $row['items'] ?? '[]';
			$item_count    = $this->get_item_count( $items_json );
			$items_by_type = $this->get_items_by_type( $items_json );
			$total         = (float) ( $row['total'] ?? 0 );

			$this->grand_total      += $total;
			$this->grand_item_count += $item_count;

			$this->items[] = array(
				'transaction_id' => $row['transaction_id'] ?? '',
				'name'           => $row['name'] ?? '',
				'email'          => $row['email'] ?? '',
				'item_count'     => $item_count,
				'items_by_type'  => $items_by_type,
				'total'          => $total,
				'tx_date'        => $row['tx_date'] ?? '',
			);
		}

		$this->set_pagination_args(
			array(
				'total_items' => count( $this->items ),
				'per_page'    => 20,
			)
		);
	}

	/**
	 * Render a cell for the default column output.
	 *
	 * @param array<string, mixed> $item The current row.
	 * @param string               $column_name The column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'transaction_id':
				return esc_html( (string) ( $item['transaction_id'] ?? '' ) );
			case 'name':
				return esc_html( (string) ( $item['name'] ?? '' ) );
			case 'email':
				return esc_html( (string) ( $item['email'] ?? '' ) );
			case 'item_count':
				return esc_html( (string) ( $item['item_count'] ?? 0 ) );
			case 'items_by_type':
				return esc_html( (string) ( $item['items_by_type'] ?? '' ) );
			case 'total':
				return esc_html( '$' . number_format( (float) ( $item['total'] ?? 0 ), 2 ) );
			case 'tx_date':
				return esc_html( (string) ( $item['tx_date'] ?? '' ) );
			default:
				return '';
		}
	}

	/**
	 * Return the total item count across all ticket records.
	 *
	 * @return int
	 */
	public function get_grand_item_count(): int {
		return $this->grand_item_count;
	}

	/**
	 * Return the gross total across all ticket records.
	 *
	 * @return float
	 */
	public function get_grand_total(): float {
		return $this->grand_total;
	}

	/**
	 * Sum the quantities embedded in the JSON item list.
	 *
	 * @param string $items_json JSON string from the items column.
	 * @return int
	 */
	private function get_item_count( string $items_json ): int {
		$items = json_decode( $items_json, true );
		if ( ! is_array( $items ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['count'] ) ) {
				$count += (int) $item['count'];
			}
		}

		return $count;
	}

	/**
	 * Format the quantities grouped by ticket type from the JSON item list.
	 *
	 * @param string $items_json JSON string from the items column.
	 * @return string
	 */
	private function get_items_by_type( string $items_json ): string {
		$items = json_decode( $items_json, true );
		if ( ! is_array( $items ) ) {
			return '';
		}

		$counts = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['type'], $item['count'] ) ) {
				continue;
			}

			$type = (string) $item['type'];
			if ( '' === $type ) {
				continue;
			}
			$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + (int) $item['count'];
		}

		$formatted_counts = array();
		foreach ( $counts as $type => $count ) {
			$formatted_counts[] = $type . ': ' . $count;
		}

		return implode( ', ', $formatted_counts );
	}
}
