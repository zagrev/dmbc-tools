<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the DMBC Tools plugin metadata.
 */
final class PluginTest extends TestCase {
	/**
	 * @covers \dmbc_tools\Plugin
	 */
	public function test_plugin_file_contains_expected_metadata(): void {
		$contents = \file_get_contents( dirname( __DIR__ ) . '/dmbc-tools.php' );

		$this->assertIsString( $contents );
		$this->assertStringContainsString( 'Plugin Name: DMBC Tools', $contents );
		$this->assertStringContainsString( 'Text Domain: dmbc-tools', $contents );
	}
}
