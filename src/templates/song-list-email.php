<?php
/**
 * Song list notification email template.
 *
 * @package DmbcTools
 *
 * @var string $song_list_title
 * @var string $rehearsal_date
 * @var array  $items
 * @var string $song_library_path
 * @var string $notes
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<div style="max-width: 720px; margin: 0 auto; padding: 24px; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #222222; background-color: #ffffff;">
	<h1 style="margin: 0 0 20px; font-size: 24px; line-height: 1.3; font-weight: 600;"><?php echo \esc_html( $song_list_title ); ?> for <?php echo \esc_html( $rehearsal_date ); ?></h1>
	<?php if ( ! empty( $items ) ) : ?>
		<h2 style="margin: 0 0 12px; font-size: 20px; line-height: 1.4;"><?php \esc_html_e( 'Rehearsal items', 'dmbc-extras' ); ?></h2>
		<ul style="margin: 0 0 24px; padding-left: 24px;">
			<?php foreach ( $items as $item ) : ?>
				<?php if ( DmbcTools\SongList::TYPE_NOTE === $item['type'] ) : ?>
					<li style="margin-bottom: 8px; white-space: pre-line;"><?php echo \esc_html( $item['value'] ); ?></li>
				<?php else : ?>
					<?php
					$song_path     = $item['value'];
					$song_url_path = DmbcTools\SongListView::convert_full_path_to_relative( WP_CONTENT_DIR, $song_path );
					$song_url      = \content_url( "$song_library_path/$song_url_path" );
					?>
					<li style="margin-bottom: 8px;"><a href="<?php echo \esc_url( $song_url ); ?>" style="color: #2271b1; text-decoration: underline;"><?php echo \esc_html( $song_path ); ?></a></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p><?php \esc_html_e( 'No songs selected for this list.', 'dmbc-extras' ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== trim( $notes ) ) : ?>
		<h2 style="margin: 0 0 12px; font-size: 20px; line-height: 1.4;"><?php \esc_html_e( 'Notes', 'dmbc-extras' ); ?></h2>
		<div><?php echo \wp_kses_post( $notes ); ?></div>
	<?php endif; ?>
</div>