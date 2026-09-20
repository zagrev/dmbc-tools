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
	</h1>
	<form method="post">
	<?php $song_list_table->display(); ?>
	</form>
</div>
