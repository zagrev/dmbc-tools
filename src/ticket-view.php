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
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Ticket Purchases', 'dmbc-tools' ); ?></h1>
			<hr class="wp-header-end">
			<div class="notice notice-info inline" style="margin: 12px 0;">
				<p>
					<strong><?php esc_html_e( 'Total Items:', 'dmbc-tools' ); ?></strong>
					<?php echo esc_html( (string) $this->ticket_table->get_grand_item_count() ); ?>
					&nbsp;|&nbsp;
					<strong><?php esc_html_e( 'Total Sales:', 'dmbc-tools' ); ?></strong>
					<?php echo esc_html( '$' . number_format( $this->ticket_table->get_grand_total(), 2 ) ); ?>
				</p>
			</div>
			<form method="post">
				<?php $this->ticket_table->display(); ?>
			</form>
		</div>
		<?php
	}
}
