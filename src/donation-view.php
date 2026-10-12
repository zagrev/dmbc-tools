<?php
/**
 * Donation admin page.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the administrator-only donation records.
 */
class DonationView {
	/**
	 * Render the donation table page.
	 *
	 * @return void
	 */
	public function render_donation_table_page(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'You do not have permission to view donations.', 'dmbc-tools' ), '', array( 'response' => 403 ) );
		}
		$range      = '30';
		$end_date   = new \DateTimeImmutable( \current_time( 'Y-m-d' ) );
		$start_date = $end_date->modify( '-29 days' );
		if ( isset( $_GET['donation_range'] ) ) {
			if ( ! isset( $_GET['dmbc_donation_filter_nonce'] ) || ! is_string( $_GET['dmbc_donation_filter_nonce'] )
				|| ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_GET['dmbc_donation_filter_nonce'] ) ), 'dmbc_filter_donations' ) ) {
				\wp_die( \esc_html__( 'Invalid donation filter request. Please reload the Donations page.', 'dmbc-tools' ), '', array( 'response' => 403 ) );
			}
			$range = is_string( $_GET['donation_range'] ) ? \sanitize_text_field( \wp_unslash( $_GET['donation_range'] ) ) : '';
			if ( ! in_array( $range, array( '30', '90', 'custom' ), true ) ) {
				\wp_die( \esc_html__( 'Please select a valid donation date range.', 'dmbc-tools' ) );
			}
			if ( 'custom' === $range ) {
				$start_value = isset( $_GET['donation_start'] ) && is_string( $_GET['donation_start'] ) ? \sanitize_text_field( \wp_unslash( $_GET['donation_start'] ) ) : '';
				$end_value   = isset( $_GET['donation_end'] ) && is_string( $_GET['donation_end'] ) ? \sanitize_text_field( \wp_unslash( $_GET['donation_end'] ) ) : '';
				$start_date  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start_value );
				$end_date    = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end_value );
				if ( ! $start_date || ! $end_date || $start_date->format( 'Y-m-d' ) !== $start_value || $end_date->format( 'Y-m-d' ) !== $end_value || $start_date > $end_date ) {
					\wp_die( \esc_html__( 'Please enter valid start and end dates, with the start date on or before the end date.', 'dmbc-tools' ) );
				}
			} else {
				$start_date = $end_date->modify( '90' === $range ? '-89 days' : '-29 days' );
			}
		}
		$donation_table = new DonationTable();
		$donation_table->prepare_items( $start_date, $end_date );
		require __DIR__ . '/templates/admin/donation-table-page.php';
	}
}
