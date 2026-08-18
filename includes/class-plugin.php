<?php
namespace DmbcTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private const VERSION = '0.1.0';
	private const OPTION_VERSION = 'dmbc_tools_version';
	private static ?self $instance = null;

	private function __construct() {
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function run(): void {
		register_activation_hook( DMBC_TOOLS_PLUGIN_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( DMBC_TOOLS_PLUGIN_FILE, [ $this, 'deactivate' ] );
		register_uninstall_hook( DMBC_TOOLS_PLUGIN_FILE, [ self::class, 'uninstall' ] );
	}

	public function activate(): void {
		add_option( self::OPTION_VERSION, self::VERSION );
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function uninstall(): void {
		delete_option( self::OPTION_VERSION );
	}
}
