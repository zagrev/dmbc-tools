<?php
/**
 * Ticket table admin page template.
 *
 * @package DmbcTools
 *
 * @var DmbcTools\TicketTable $ticket_table
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<style>
	@media print {
		body * {
			visibility: hidden;
		}

		.dmbc-ticket-print-area,
		.dmbc-ticket-print-area * {
			visibility: visible;
		}

		.dmbc-ticket-print-area {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
		}

		.dmbc-ticket-print-actions,
		.dmbc-ticket-print-area .tablenav,
		.dmbc-ticket-print-area .search-box,
		.dmbc-ticket-print-area .row-actions {
			display: none !important;
		}
	}
</style>
<div class="wrap">
	<div class="dmbc-ticket-print-actions">
		<button type="button" class="button button-primary" onclick="window.print();">
			<?php esc_html_e( 'Print tickets', 'dmbc-tools' ); ?>
		</button>
	</div>
	<div class="dmbc-ticket-print-area">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Ticket Purchases', 'dmbc-tools' ); ?></h1>
		<hr class="wp-header-end">
		<div class="notice notice-info inline" style="margin: 12px 0;">
			<p>
				<strong><?php esc_html_e( 'Total Items:', 'dmbc-tools' ); ?></strong>
				<?php echo esc_html( (string) $ticket_table->get_grand_item_count() ); ?>
				&nbsp;|&nbsp;
				<strong><?php esc_html_e( 'Total Sales:', 'dmbc-tools' ); ?></strong>
				<?php echo esc_html( '$' . number_format( $ticket_table->get_grand_total(), 2 ) ); ?>
			</p>
		</div>
		<form method="post">
			<?php $ticket_table->display(); ?>
		</form>
	</div>
</div>
