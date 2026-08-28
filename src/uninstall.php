<?php
/** This file handles the uninstallation of the DMBC Tools plugin. */
declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * This file will be called automatically during the uninstall of the plugin.
 */
if ( \class_exists( Plugin::class ) ) {
	Plugin::uninstall();
}
