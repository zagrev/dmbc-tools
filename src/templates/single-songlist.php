<?php
/**
 * The Template for displaying single 'dmbc-songlist' custom post types.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined . This file( ' . __FILE__ . ' ) should not be accessed directly . ' . PHP_EOL;
	exit;
}

$dmbc_is_member = SongListPlaylist::current_user_is_member();

$dmbc_is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

\wp_enqueue_style(
	'dmbc-single-songlist',
	\plugin_dir_url( __FILE__ ) . 'single-songlist.css',
	array(),
	Plugin::VERSION
);

\wp_enqueue_script(
	'dmbc-single-songlist',
	\plugin_dir_url( __FILE__ ) . 'single-songlist.js',
	array(),
	Plugin::VERSION,
	true
);

if ( $dmbc_is_block_theme ) :
	?>
<!doctype html>
<html <?php \language_attributes(); ?>>
<head>
	<meta charset="<?php \bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php \wp_head(); ?>
</head>
<body <?php \body_class(); ?>>
	<?php \wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php \block_template_part( 'header' ); ?>
	<?php
else :
	\get_header();
endif;
?>

<div id="primary" class="content-area dmbc-songlist-container">
	<main id="main" class="site-main" role="main">

		<?php
		while ( \have_posts() ) :
			\the_post();

			$songlist_id             = \get_the_ID();
			$rehearsal_date          = (string) \get_post_meta( $songlist_id, Plugin::PERFORMANCE_DATE_META_KEY, true );
			$items                   = SongList::normalize_items( \get_post_meta( $songlist_id, Plugin::SONGS_META_KEY, true ) );
			$notes                   = (string) \get_post_meta( $songlist_id, Plugin::NOTES_META_KEY, true );
			$download_groups         = $dmbc_is_member ? SongListPlaylist::get_download_groups( $items ) : array();
			$download_groups_by_song = array();
			foreach ( $download_groups as $download_group ) {
				$download_groups_by_song[ $download_group['song'] ] = $download_group;
			}
			$playlist_file_urls = $dmbc_is_member ? SongListPlaylist::get_or_update_playlist_from_download_groups( $songlist_id, $download_groups ) : array();
			$playlist_filename  = \sanitize_file_name( \get_the_title() . '.m3u' );
			?>

			<article id="post-<?php \the_ID(); ?>" <?php \post_class( 'dmbc-songlist-article' ); ?>>
				
				<header class="dmbc-songlist-header">
					<h1 class="dmbc-songlist-title"><?php echo \esc_html( \get_the_title() ); ?></h1>
					<?php if ( ! empty( $rehearsal_date ) ) : ?>
						<p class="dmbc-songlist-date">
							<?php \esc_html_e( 'Rehearsal date:', 'dmbc-tools' ); ?>
							<time datetime="<?php echo \esc_attr( $rehearsal_date ); ?>"><?php echo \esc_html( $rehearsal_date ); ?></time>
						</p>
					<?php endif; ?>
				</header>

				<div class="dmbc-songlist-content">
					<?php if ( ! empty( $items ) ) : ?>
						<ul class="dmbc-songlist-items">
							<?php foreach ( $items as $item ) : ?>
								<?php if ( SongList::TYPE_NOTE === $item['type'] ) : ?>
									<li class="dmbc-songlist-note"><?php echo \esc_html( $item['value'] ); ?></li>
								<?php else : ?>
									<?php
									$song_path      = \wp_normalize_path( (string) $item['value'] );
									$song_url       = SongListPlaylist::get_song_folder_url( $song_path );
									$download_group = $download_groups_by_song[ $song_path ] ?? null;
									?>
									<li class="dmbc-songlist-song">
										<?php if ( ! empty( $download_group ) ) : ?>
											<details class="dmbc-songlist-song-details">
												<summary class="dmbc-songlist-song-summary"><?php echo \esc_html( $song_path ); ?></summary>
												<div class="dmbc-songlist-song-panel">
													<a class="dmbc-songlist-folder-link" href="<?php echo \esc_url( $song_url ); ?>"><?php \esc_html_e( 'Open song folder', 'dmbc-tools' ); ?></a>
													<ul class="dmbc-songlist-download-files">
														<?php foreach ( $download_group['files'] as $download_file ) : ?>
															<li>
																<a href="<?php echo \esc_url( $download_file['url'] ); ?>" download><?php echo \esc_html( $download_file['name'] ); ?></a>
															</li>
														<?php endforeach; ?>
													</ul>
												</div>
											</details>
										<?php else : ?>
											<a href="<?php echo \esc_url( $song_url ); ?>"><?php echo \esc_html( $song_path ); ?></a>
										<?php endif; ?>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p><?php \esc_html_e( 'No songs selected for this list.', 'dmbc-tools' ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $notes ) ) : ?>
						<section class="dmbc-songlist-notes" aria-label="<?php \esc_attr_e( 'Song list notes', 'dmbc-tools' ); ?>">
							<?php echo wp_kses_post( wpautop( $notes ) ); ?>
						</section>
					<?php endif; ?>

					<?php if ( ! empty( $playlist_file_urls ) ) : ?>
						<p class="dmbc-songlist-playlist">
							<a class="dmbc-songlist-playlist-download" href="#" download="<?php echo \esc_attr( $playlist_filename ); ?>" data-playlist-urls="<?php echo \esc_attr( wp_json_encode( $playlist_file_urls ) ); ?>">
								<?php \esc_html_e( 'Download playlist', 'dmbc-tools' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>

			</article>

			<?php
		endwhile;
		?>

	</main>
</div>

<?php
if ( $dmbc_is_block_theme ) :
	block_template_part( 'footer' );
	?>
</div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
else :
	get_sidebar();
	get_footer();
endif;
