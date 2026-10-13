<?php
/**
 * Member update email formatting.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * Format a member update using the website's single-post layout for email.
 *
 * @param \WP_Post $post The member update to format.
 * @return string The formatted HTML email body.
 */
function format_member_update_post( \WP_Post $post ): string {
	$title   = \esc_html( \get_the_title( $post ) );
	$date    = \esc_html( \get_the_date( 'F j, Y', $post ) );
	$content = \apply_filters( 'the_content', $post->post_content );
	$styles  = \function_exists( 'wp_get_global_stylesheet' ) ? \wp_get_global_stylesheet() : '';

	return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>{$styles}</style>
</head>
<body style="margin:0; padding:0; background:#ffffff; color:#111111; font-family:Manrope, Arial, sans-serif; font-size:20px; font-weight:400; line-height:1.65;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
		<tr>
			<td align="center" style="padding:24px;">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:760px; border-collapse:collapse;">
					<tr>
						<td>
							<h1 style="margin:0 0 24px; color:#111111; font-family:Manrope, Arial, sans-serif; font-size:40px; font-weight:400; line-height:1.15;">{$title}</h1>
							<p style="margin:0 0 24px; color:#111111; font-size:20px; line-height:1.65;">{$date}</p>
							<div style="color:#111111; font-family:Manrope, Arial, sans-serif; font-size:20px; line-height:1.65;">{$content}</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
HTML;
}
