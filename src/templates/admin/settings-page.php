<?php
/**
 * Plugin settings page template.
 *
 * @package DmbcTools
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'DMBC Tools Settings', 'dmbc-tools' ); ?></h1>
	<form method="post" action="options.php">
	<?php
	settings_fields( 'settings_group' );
	do_settings_sections( 'settings' );
	submit_button();
	?>
	</form>
</div>
