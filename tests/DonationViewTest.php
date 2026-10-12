<?php

require_once dirname( __DIR__ ) . '/src/donation-table.php';
require_once dirname( __DIR__ ) . '/src/donation-view.php';

use DmbcTools\DonationTable;
use DmbcTools\DonationView;

/** @covers \DmbcTools\DonationView
 * @covers \DmbcTools\DonationTable
 */
final class DonationViewTest extends DmbcUnitTestBase {
	public function test_donations_page_displays_escaped_rows_and_totals(): void {
		$GLOBALS['dmbc_test_state']['wpdb_results'] = array(
			array( 'name' => '<script>Donor</script>', 'email' => 'donor@example.com', 'transaction_id' => 'donation-1', 'tx_date' => '2026-10-12 12:00:00', 'total' => '42.50' ),
			array( 'name' => 'Second Donor', 'total' => '0.10' ),
			array( 'name' => 'Third Donor', 'total' => '0.20' ),
		);
		ob_start();
		( new DonationView() )->render_donation_table_page();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Donations', $html );
		$this->assertStringContainsString( 'Donation Count:', $html );
		$this->assertStringContainsString( '$42.80', $html );
		$this->assertStringContainsString( '&lt;script&gt;Donor&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>Donor</script>', $html );
		$this->assertStringContainsString( 'donor@example.com', $html );
		$this->assertStringContainsString( 'donation-1', $html );
		$this->assertSame( array( 'wp_dmbc_donation', '2026-08-04 00:00:00', '2026-09-03 00:00:00' ), $GLOBALS['dmbc_test_state']['wpdb_prepare'][0]['args'] );
		$this->assertStringContainsString( 'value="30" selected="selected"', $html );
		$this->assertStringContainsString( 'tx_date >= %s AND tx_date < %s', $GLOBALS['dmbc_test_state']['wpdb_prepare'][0]['query'] );
	}

	public function test_donations_page_rejects_non_administrators_before_querying(): void {
		$this->set_current_user_can( false );
		try {
			( new DonationView() )->render_donation_table_page();
			$this->fail( 'Donation records must require administrator permission.' );
		} catch ( Dmbc_Test_Wp_Die_Exception $exception ) {
			$this->assertStringContainsString( 'permission', $exception->getMessage() );
		}
		$this->assertEmpty( $GLOBALS['dmbc_test_state']['wpdb_get_results'] ?? array() );
	}

	public function test_empty_donation_table_has_zero_totals_and_refreshes_without_cache(): void {
		$table = new DonationTable();
		$table->prepare_items();
		$this->assertSame( 0, $table->get_donation_count() );
		$this->assertSame( 0.0, $table->get_grand_total() );
		$GLOBALS['dmbc_test_state']['wpdb_results'] = array( array( 'total' => '10.00' ) );
		$table->prepare_items();
		$this->assertSame( 1, $table->get_donation_count() );
		$this->assertSame( 10.0, $table->get_grand_total() );
	}

	public function test_donation_query_failures_are_reported(): void {
		$GLOBALS['dmbc_test_state']['wpdb_query_error'] = 'Database unavailable';
		$this->expectException( Dmbc_Test_Wp_Die_Exception::class );
		$this->expectExceptionMessage( 'Unable to load donations.' );
		( new DonationTable() )->prepare_items();
	}

	public static function selected_ranges(): array {
		return array(
			array( '30', '', '', '2026-08-04 00:00:00', '2026-09-03 00:00:00' ),
			array( '90', '', '', '2026-06-05 00:00:00', '2026-09-03 00:00:00' ),
			array( 'custom', '2026-01-01', '2026-01-31', '2026-01-01 00:00:00', '2026-02-01 00:00:00' ),
			array( 'custom', '2024-02-29', '2024-02-29', '2024-02-29 00:00:00', '2024-03-01 00:00:00' ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'selected_ranges' )]
	public function test_selected_range_is_applied_in_sql_and_preserved_in_form( string $range, string $start, string $end, string $lower, string $upper ): void {
		$_GET = array( 'donation_range' => $range, 'donation_start' => $start, 'donation_end' => $end, 'dmbc_donation_filter_nonce' => 'valid' );
		ob_start();
		( new DonationView() )->render_donation_table_page();
		$html = (string) ob_get_clean();
		$this->assertSame( array( 'wp_dmbc_donation', $lower, $upper ), $GLOBALS['dmbc_test_state']['wpdb_prepare'][0]['args'] );
		$this->assertStringContainsString( 'value="' . $range . '" selected="selected"', $html );
		$this->assertStringContainsString( 'value="' . substr( $lower, 0, 10 ) . '"', $html );
	}

	public static function invalid_ranges(): array {
		return array(
			array( 'custom', '', '' ),
			array( 'custom', '2026-02-30', '2026-03-01' ),
			array( 'custom', '2026-01-31', '2026-01-01' ),
			array( 'custom', array( 'bad' ), '2026-01-01' ),
			array( '365', '', '' ),
			array( array( '30' ), '', '' ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalid_ranges' )]
	public function test_invalid_ranges_are_rejected_before_querying( mixed $range, mixed $start, mixed $end ): void {
		$_GET = array( 'donation_range' => $range, 'donation_start' => $start, 'donation_end' => $end, 'dmbc_donation_filter_nonce' => 'valid' );
		try {
			( new DonationView() )->render_donation_table_page();
			$this->fail( 'Invalid filters must not load donations.' );
		} catch ( Dmbc_Test_Wp_Die_Exception $exception ) {
			$this->assertNotEmpty( $exception->getMessage() );
		}
		$this->assertEmpty( $GLOBALS['dmbc_test_state']['wpdb_get_results'] ?? array() );
	}

	public function test_date_filter_rejects_invalid_nonce(): void {
		$_GET = array( 'donation_range' => '90', 'dmbc_donation_filter_nonce' => 'invalid' );
		$this->set_wp_verify_nonce_result( false );
		$this->expectException( Dmbc_Test_Wp_Die_Exception::class );
		$this->expectExceptionMessage( 'Invalid donation filter request.' );
		( new DonationView() )->render_donation_table_page();
	}
}
