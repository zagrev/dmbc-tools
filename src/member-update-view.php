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
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Member Updates', 'dmbc-tools' ); ?></h1>
			<a href="<?php echo esc_url( \admin_url( 'post-new.php?post_type=' . Plugin::MEMBER_UPDATE_POST_TYPE ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'dmbc-tools' ); ?></a>
			<hr class="wp-header-end">
			<form method="post">
				<?php $this->member_update_table->display(); ?>
			</form>
		</div>
		<?php
	}

}
