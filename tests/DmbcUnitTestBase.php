<?php

use PHPUnit\Framework\TestCase;

/**
 * Base test case for DMBC Tools plugin unit tests. Resets the WordPress
 * function stub state before each test and provides helpers for configuring it.
 */
abstract class DmbcUnitTestBase extends TestCase {
	/**
	 * Directories created during a test, cleaned up automatically afterwards.
	 *
	 * @var string[]
	 */
	private array $temp_directories = array();

	protected function setUp(): void {
		parent::setUp();

		$_POST = array();

		$GLOBALS['dmbc_test_state'] = array(
			'options'             => array(),
			'current_user_can'    => true,
			'wp_roles'            => array(
				'administrator' => array( 'name' => 'Administrator' ),
				'editor'        => array( 'name' => 'Editor' ),
				'subscriber'    => array( 'name' => 'Subscriber' ),
			),
			'nonce_valid'         => true,
			'registered_settings' => array(),
			'settings_sections'   => array(),
			'settings_fields'     => array(),
		);
	}

	protected function tearDown(): void {
		foreach ( $this->temp_directories as $directory ) {
			$this->remove_directory_tree( $directory );
		}
		$this->temp_directories = array();

		parent::tearDown();
	}

	/** Set the value returned by get_option() for the given key. */
	protected function set_option( string $key, $value ): void {
		$GLOBALS['dmbc_test_state']['options'][ $key ] = $value;
	}

	/** Control the value returned by current_user_can(). */
	protected function set_current_user_can( bool $can ): void {
		$GLOBALS['dmbc_test_state']['current_user_can'] = $can;
	}

	/** Control the roles returned by wp_roles(). */
	protected function set_wp_roles( array $roles ): void {
		$GLOBALS['dmbc_test_state']['wp_roles'] = $roles;
	}

	/** Control whether check_ajax_referer() considers the nonce valid. */
	protected function set_nonce_valid( bool $valid ): void {
		$GLOBALS['dmbc_test_state']['nonce_valid'] = $valid;
	}

	/**
	 * Create a fresh temporary directory that will be removed after the test.
	 *
	 * @return string The normalized (forward-slash) path to the directory.
	 */
	protected function create_temp_directory(): string {
		$path = str_replace( '\\', '/', sys_get_temp_dir() ) . '/dmbc-tools-test-' . uniqid();
		mkdir( $path, 0777, true );
		$this->temp_directories[] = $path;

		return $path;
	}

	/** Create nested subdirectories under $base, e.g. ['Song A' => ['Verses' => []], 'Song B' => []]. */
	protected function make_directory_tree( string $base, array $structure ): void {
		foreach ( $structure as $name => $children ) {
			$path = $base . '/' . $name;
			mkdir( $path, 0777, true );
			if ( is_array( $children ) && ! empty( $children ) ) {
				$this->make_directory_tree( $path, $children );
			}
		}
	}

	/** Recursively delete a directory tree. */
	protected function remove_directory_tree( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}
		foreach ( scandir( $path ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$item_path = $path . '/' . $item;
			if ( is_dir( $item_path ) ) {
				$this->remove_directory_tree( $item_path );
			} else {
				unlink( $item_path );
			}
		}
		rmdir( $path );
	}
}
