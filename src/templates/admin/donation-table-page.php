<?php
/**
 * Donation table page template.
 *
 * @package DmbcTools
 *
 * @var DmbcTools\DonationTable $donation_table
 * @var string $range
 * @var DateTimeImmutable $start_date
 * @var DateTimeImmutable $end_date
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Donations', 'dmbc-tools' ); ?></h1>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="dmbc-donations">
		<?php wp_nonce_field( 'dmbc_filter_donations', 'dmbc_donation_filter_nonce', false ); ?>
		<label for="donation-range"><?php esc_html_e( 'Date range', 'dmbc-tools' ); ?></label>
		<select id="donation-range" name="donation_range">
			<option value="30" <?php selected( $range, '30' ); ?>><?php esc_html_e( 'Last 30 days', 'dmbc-tools' ); ?></option>
			<option value="90" <?php selected( $range, '90' ); ?>><?php esc_html_e( 'Last 90 days', 'dmbc-tools' ); ?></option>
			<option value="custom" <?php selected( $range, 'custom' ); ?>><?php esc_html_e( 'Custom date range', 'dmbc-tools' ); ?></option>
		</select>
		<label for="donation-start"><?php esc_html_e( 'Start date (custom)', 'dmbc-tools' ); ?></label>
		<input type="date" id="donation-start" name="donation_start" value="<?php echo esc_attr( $start_date->format( 'Y-m-d' ) ); ?>">
		<label for="donation-end"><?php esc_html_e( 'End date (custom)', 'dmbc-tools' ); ?></label>
		<input type="date" id="donation-end" name="donation_end" value="<?php echo esc_attr( $end_date->format( 'Y-m-d' ) ); ?>">
		<?php submit_button( __( 'Apply date range', 'dmbc-tools' ), 'secondary', 'filter_donations', false ); ?>
	</form>
	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'Selected dates:', 'dmbc-tools' ); ?></strong>
			<?php echo esc_html( $start_date->format( 'Y-m-d' ) . ' - ' . $end_date->format( 'Y-m-d' ) ); ?>
			&nbsp;|&nbsp;
			<strong><?php esc_html_e( 'Donation Count:', 'dmbc-tools' ); ?></strong>
			<?php echo esc_html( (string) $donation_table->get_donation_count() ); ?>
			&nbsp;|&nbsp;
			<strong><?php esc_html_e( 'Total Donations:', 'dmbc-tools' ); ?></strong>
			<?php echo esc_html( '$' . number_format( $donation_table->get_grand_total(), 2 ) ); ?>
		</p>
	</div>
	<?php $donation_table->display(); ?>
</div>
