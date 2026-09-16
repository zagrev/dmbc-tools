<?php
/**
 * The Template for displaying the song-list archive.
 *
 * @package DmbcTools
 */

declare(strict_types=1);

use DmbcTools\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

<div id="primary" class="content-area dmbc-songlist-container dmbc-songlist-archive">
	<main id="main" class="site-main" role="main">
		<header class="dmbc-songlist-archive-header">
			<h1 class="dmbc-songlist-title"><?php esc_html_e( 'Song Lists', 'dmbc-tools' ); ?></h1>
		</header>

		<?php if ( ! is_user_logged_in() || ! current_user_can( Plugin::CAP_VIEW_SONGLISTS ) ) : ?>
			<p><?php esc_html_e( 'Please sign in with a member account to view song lists.', 'dmbc-tools' ); ?></p>
		<?php elseif ( have_posts() ) : ?>
			<ul class="dmbc-songlist-archive-items">
				<?php
				while ( have_posts() ) :
					the_post();

					$songlist_id    = get_the_ID();
					$rehearsal_date = (string) get_post_meta( $songlist_id, Plugin::PERFORMANCE_DATE_META_KEY, true );
					?>
					<li <?php post_class( 'dmbc-songlist-archive-item' ); ?>>
						<a class="dmbc-songlist-archive-link" href="<?php echo esc_url( get_permalink() ); ?>">
							<?php if ( ! empty( $rehearsal_date ) ) : ?>
								<time class="dmbc-songlist-archive-date" datetime="<?php echo esc_attr( $rehearsal_date ); ?>"><?php echo esc_html( $rehearsal_date ); ?></time>
							<?php endif; ?>
							<span class="dmbc-songlist-archive-title"><?php echo esc_html( get_the_title() ); ?></span>
						</a>
					</li>
					<?php
				endwhile;
				?>
			</ul>

			<?php the_posts_navigation(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No song lists are available yet.', 'dmbc-tools' ); ?></p>
		<?php endif; ?>
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
