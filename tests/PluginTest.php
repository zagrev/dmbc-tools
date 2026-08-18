<?php

use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase {
	public function test_plugin_file_contains_expected_metadata(): void {
		$contents = file_get_contents( dirname( __DIR__ ) . '/dmbc-tools.php' );

		$this->assertIsString( $contents );
		$this->assertStringContainsString( 'Plugin Name: DMBC Tools', $contents );
		$this->assertStringContainsString( 'Text Domain: dmbc-tools', $contents );
	}
}
