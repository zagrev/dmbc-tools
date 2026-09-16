<?php
/**
 * The Template for displaying single 'dmbc-songlist' custom post types.
 *
 * @package DmbcTools
 */

declare(strict_types=1);

use DmbcTools\Plugin;
use DmbcTools\SongList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dmbc_normalize_path = static function ( string $path ): string {
	if ( function_exists( 'wp_normalize_path' ) ) {
		return wp_normalize_path( $path );
	}

	return str_replace( '\\', '/', $path );
};

$dmbc_is_absolute_path = static function ( string $path ): bool {
	$path = str_replace( '\\', '/', $path );

	return 1 === preg_match( '#^(?:[A-Za-z]:)?/#', $path );
};

$dmbc_relative_to_content = static function ( string $path ) use ( $dmbc_normalize_path ): string {
	$path = $dmbc_normalize_path( $path );

	if ( defined( 'WP_CONTENT_DIR' ) ) {
		$content_dir = rtrim( $dmbc_normalize_path( WP_CONTENT_DIR ), '/' ) . '/';
		if ( str_starts_with( $path, $content_dir ) ) {
			return ltrim( substr( $path, strlen( $content_dir ) ), '/' );
		}
	}

	return ltrim( $path, '/' );
};

$dmbc_encode_url_path = static function ( string $path ): string {
	$parts = array_map( 'rawurlencode', explode( '/', trim( $path, '/' ) ) );

	return implode( '/', $parts );
};

$dmbc_make_content_relative_url = static function ( string $path ) use ( $dmbc_encode_url_path ): string {
	$url = content_url( $dmbc_encode_url_path( $path ) );

	return function_exists( 'wp_make_link_relative' ) ? wp_make_link_relative( $url ) : $url;
};

$dmbc_is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

wp_enqueue_style(
	'dmbc-single-songlist',
	plugin_dir_url( __FILE__ ) . 'single-songlist.css',
	array(),
	Plugin::VERSION
);

if ( $dmbc_is_block_theme ) :
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php block_template_part( 'header' ); ?>
	<?php
else :
	get_header();
endif;
?>

<div id="primary" class="content-area dmbc-songlist-container">
	<main id="main" class="site-main" role="main">

		<?php
		while ( have_posts() ) :
			the_post();

			$songlist_id            = get_the_ID();
			$rehearsal_date         = (string) get_post_meta( $songlist_id, Plugin::PERFORMANCE_DATE_META_KEY, true );
			$items                  = SongList::normalize_items( get_post_meta( $songlist_id, Plugin::SONGS_META_KEY, true ) );
			$notes                  = (string) get_post_meta( $songlist_id, Plugin::NOTES_META_KEY, true );
			$song_library_directory = trim( str_replace( '\\', '/', (string) get_option( Plugin::OPTION_SONGLIST_DIRECTORY, 'dmbc-song-library' ) ), '/' );
			if ( '' === $song_library_directory ) {
				$song_library_directory = 'dmbc-song-library';
			}
			$song_library_path = $dmbc_is_absolute_path( $song_library_directory )
				? $dmbc_relative_to_content( $song_library_directory )
				: $song_library_directory;
			?>

			<article id="post-<?php the_ID(); ?>" <?php post_class( 'dmbc-songlist-article' ); ?>>
				
				<header class="dmbc-songlist-header">
					<h1 class="dmbc-songlist-title"><?php echo esc_html( get_the_title() ); ?></h1>
					<?php if ( ! empty( $rehearsal_date ) ) : ?>
						<p class="dmbc-songlist-date">
							<?php esc_html_e( 'Rehearsal date:', 'dmbc-tools' ); ?>
							<time datetime="<?php echo esc_attr( $rehearsal_date ); ?>"><?php echo esc_html( $rehearsal_date ); ?></time>
						</p>
					<?php endif; ?>
				</header>

				<div class="dmbc-songlist-content">
					<?php if ( ! empty( $items ) ) : ?>
						<ul class="dmbc-songlist-items">
							<?php foreach ( $items as $item ) : ?>
								<?php if ( SongList::TYPE_NOTE === $item['type'] ) : ?>
									<li class="dmbc-songlist-note"><?php echo esc_html( $item['value'] ); ?></li>
								<?php else : ?>
									<?php
									$song_path     = $dmbc_normalize_path( (string) $item['value'] );
									$song_url_path = $dmbc_is_absolute_path( $song_path )
										? $dmbc_relative_to_content( $song_path )
										: trim( $song_library_path . '/' . ltrim( $song_path, '/' ), '/' );
									$song_url      = $dmbc_make_content_relative_url( rtrim( $song_url_path, '/' ) . '/' );
									?>
									<li class="dmbc-songlist-song">
										<a href="<?php echo esc_url( $song_url ); ?>"><?php echo esc_html( $song_path ); ?></a>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p><?php esc_html_e( 'No songs selected for this list.', 'dmbc-tools' ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $notes ) ) : ?>
						<section class="dmbc-songlist-notes" aria-label="<?php esc_attr_e( 'Song list notes', 'dmbc-tools' ); ?>">
							<?php echo wp_kses_post( wpautop( $notes ) ); ?>
						</section>
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
