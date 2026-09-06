<?php
declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * Plugin Name: DMBC Tools
 * Plugin URI: https://github.com/zagrev/dmbc-tools
 * Description: Shared tools for the Dayton Metro Barbershop Chorus WordPress site.
 * Version: 1.1.7
 * Author: Steve Betts
 * Author URI: https://github.com/zagrev
 * Text Domain: dmbc-tools
 * Domain Path: /languages
 * Requires PHP: 8.0
 * License: CC BY-NC-ND
 * License URI: https://creativecommons.org/licenses/by-nc-nd/4.0/
 */


define( 'DMBC_TOOLS_PLUGIN_FILE', __FILE__ );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}


use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/zagrev/dmbc-tools',
	__FILE__,
	'dmbc-tools'
);

require_once __DIR__ . '/src/plugin.php';
Plugin::instance()->run();
