<?php

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/wp-stubs.php';
require_once dirname( __DIR__ ) . '/src/admin/settings-edit.php';
require_once __DIR__ . '/DmbcUnitTestBase.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', str_replace( '\\', '/', sys_get_temp_dir() ) . '/dmbc-tools-test-abspath/' );
}

require_once dirname( __DIR__ ) . '/src/plugin.php';

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	// Use forward slashes so the plugin's absolute-path detection behaves the same as on a real WP install.
	define( 'WP_CONTENT_DIR', str_replace( '\\', '/', sys_get_temp_dir() ) . '/dmbc-tools-test-wp-content' );
}

// Fixture layout shared by tests that rely on the default WP_CONTENT_DIR / song library location.
$dmbc_fixture_library = WP_CONTENT_DIR . '/dmbc-song-library';
if ( ! is_dir( $dmbc_fixture_library . '/Song A/Verses' ) ) {
	mkdir( $dmbc_fixture_library . '/Song A/Verses', 0777, true );
}
if ( ! is_dir( $dmbc_fixture_library . '/Song B' ) ) {
	mkdir( $dmbc_fixture_library . '/Song B', 0777, true );
}
