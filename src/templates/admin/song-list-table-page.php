<?php
/**
 * Song list table page template.
 *
 * @package DmbcTools
 *
 * @var DmbcTools\SongListTable $song_list_table
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<div class="wrap">
	<h1>
		<?php esc_html_e( 'Rehearsal Song Lists', 'dmbc-extras' ); ?>
		<?php if ( \current_user_can( \DmbcTools\Plugin::CAP_EDIT_SONGLIST ) || \current_user_can( 'manage_options' ) ) : ?>
			<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=dmbc-songlist-edit' ) ); ?>"
				class="page-title-action"><?php \esc_html_e( 'Add Song List', 'dmbc-extras' ); ?></a>
		<?php endif; ?>
	</h1>
	<?php
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only confirmation message; no state is changed.
	if ( isset( $_GET['dmbc_song_list_saved'] ) && is_string( $_GET['dmbc_song_list_saved'] )
		&& ( \current_user_can( \DmbcTools\Plugin::CAP_EDIT_SONGLIST ) || \current_user_can( 'manage_options' ) ) ) :
		$saved_status = \sanitize_key( \wp_unslash( $_GET['dmbc_song_list_saved'] ) );
		if ( in_array( $saved_status, array( 'draft', 'publish' ), true ) ) :
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo \esc_html( 'draft' === $saved_status ? __( 'Rehearsal song list saved as a draft.', 'dmbc-extras' ) : __( 'Rehearsal song list published successfully.', 'dmbc-extras' ) ); ?></p>
			</div>
			<?php
		endif;
	endif;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	?>
	<form method="post">
		<?php \wp_nonce_field( 'dmbc_song_list_request' ); ?>
		<?php $song_list_table->display(); ?>
	</form>
</div>