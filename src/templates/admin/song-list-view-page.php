<?php
/**
 * Song list detail page template.
 *
 * @package DmbcTools
 *
 * @var string $song_list_title
 * @var string $rehearsal_date
 * @var array  $items
 * @var string $song_library_path
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<div class="dmbc-song-list-view">
	<h1><?php echo esc_html( $song_list_title ); ?> for <?php echo esc_html( $rehearsal_date ); ?></h1>

	<?php if ( ! empty( $items ) ) : ?>
		<h2>
		<?php esc_html_e( 'Rehearsal items', 'dmbc-extras' ); ?>
		</h2>
		<ul>
			<?php foreach ( $items as $item ) : ?>
				<?php if ( DmbcTools\SongList::TYPE_NOTE === $item['type'] ) : ?>
				<li class="dmbc-rehearsal-note"><?php echo esc_html( $item['value'] ); ?></li>
				<?php else : ?>
					<?php
					$song_path     = $item['value'];
					$song_url_path = DmbcTools\SongListView::convert_full_path_to_relative( WP_CONTENT_DIR, $song_path );
					$song_url      = \content_url( "$song_library_path/$song_url_path" );
					?>
				<li><a href="<?php echo \esc_url( $song_url ); ?>"><?php echo esc_html( $song_path ); ?></a></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<?php else : ?>
		<p><?php esc_html_e( 'No songs selected for this list.', 'dmbc-extras' ); ?></p>
	<?php endif; ?>
</div>
