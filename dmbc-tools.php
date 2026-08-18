<?php
/**
 * Plugin Name: DMBC Tools
 * Plugin URI: https://github.com/zagrev/dmbc-tools
 * Description: Shared tools for the Dayton Metro Barbershop Chorus WordPress site.
 * Version: 0.1.0
 * Author: Steve Betts
 * Author URI: https://github.com/zagrev
 * Text Domain: dmbc-tools
 * Domain Path: /languages
 * Requires PHP: 8.0
 * License: CC BY-NC-ND
 * License URI: https://creativecommons.org/licenses/by-nc-nd/4.0/
 */

namespace DmbcTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DMBC_TOOLS_PLUGIN_FILE', __FILE__ );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/class-plugin.php';

Plugin::instance()->run();
