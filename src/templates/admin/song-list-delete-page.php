<?php
/**
 * Song list delete confirmation page template.
 *
 * @package DmbcTools
 *
 * @var int $edit_id
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<form method="post" action="" id="dmbc_delete_song_list_form">
	<?php \wp_nonce_field( 'dmbc_delete_song_list', 'dmbc_song_list_delete_nonce' ); ?>
	<input type="hidden" name="dmbc_song_list_id" value="<?php echo esc_attr( $edit_id ); ?>">
	<?php
	\submit_button(
		__( 'Delete song list?', 'dmbc-extras' ),
		'warn large danger btn-danger',
		'dmbc_delete_song_list',
		false,
		'style="background-color:#d63638 !important; border-color:#d63638 !important; color:#fff !important;
	padding:0.75rem1.25rem; font-size:1rem;"'
	);
	?>
</form>
