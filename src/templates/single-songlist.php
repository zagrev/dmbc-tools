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

			$songlist_id    = \get_the_ID();
			$rehearsal_date = (string) \get_post_meta( $songlist_id, Plugin::PERFORMANCE_DATE_META_KEY, true );
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

				<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_songlist_post() escapes dynamic values and returns plugin-owned markup. ?>
				<?php echo Plugin::render_songlist_post( (int) $songlist_id ); ?>

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
