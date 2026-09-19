<?php
/**
 * TicketView class file.
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
 * Renders the admin page for Deluxe ticket records.
 */
class TicketView {
	/**
	 * The table layout.
	 *
	 * @var TicketTable
	 */
	private TicketTable $ticket_table;

	/**
	 * Create the table used by the list page.
	 *
	 * @return void
	 */
	private function create_ticket_table(): void {
		if ( ! isset( $this->ticket_table ) ) {
			$this->ticket_table = new TicketTable();
		}
	}

	/**
	 * Render the ticket table page.
	 *
	 * @return void
	 */
	public function render_ticket_table_page(): void {
		// TODO add access control check here.
		$this->create_ticket_table();
		$this->ticket_table->prepare_items();
		$ticket_table = $this->ticket_table;
		require __DIR__ . '/templates/admin/ticket-table-page.php';
	}
}
