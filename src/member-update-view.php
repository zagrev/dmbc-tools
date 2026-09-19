<?php
/**
 * MemberUpdateView class file.
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
 * Renders and saves member update administration pages.
 */
class MemberUpdateView {
	/**
	 * The table layout.
	 *
	 * @var MemberUpdateTable
	 */
	private MemberUpdateTable $member_update_table;

	/**
	 * Create the table used by the list page.
	 *
	 * @return void
	 */
	private function create_member_update_table(): void {
		if ( ! isset( $this->member_update_table ) ) {
			$this->member_update_table = new MemberUpdateTable();
		}
	}

	/**
	 * Render the member updates table page.
	 *
	 * @return void
	 */
	public function render_member_update_table_page(): void {
		$this->create_member_update_table();
		$this->member_update_table->prepare_items();
		$member_update_table = $this->member_update_table;
		require __DIR__ . '/templates/admin/member-update-table-page.php';
	}
}
