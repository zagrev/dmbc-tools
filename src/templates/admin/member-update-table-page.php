<?php
/**
 * Member update table admin page template.
 *
 * @package DmbcTools
 *
 * @var DmbcTools\MemberUpdateTable $member_update_table
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Member Updates', 'dmbc-tools' ); ?></h1>
	<a href="<?php echo esc_url( \admin_url( 'post-new.php?post_type=' . DmbcTools\Plugin::MEMBER_UPDATE_POST_TYPE ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'dmbc-tools' ); ?></a>
	<hr class="wp-header-end">
	<form method="post">
		<?php $member_update_table->display(); ?>
	</form>
</div>
