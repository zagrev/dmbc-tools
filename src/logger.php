<?php
/**
 * Application-level logger for the DMBC Tools plugin.
 *
 * This is a standalone PHP logging utility that follows a lightweight
 * Log4j-style API while integrating with WordPress/PHP logging.
 *
 * @package DmbcTools
 */

declare(strict_types=1);

namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined . This file( ' . __FILE__ . ' ) should not be accessed directly . ' . PHP_EOL;
	exit;
}

/**
 * Lightweight application logger inspired by Log4j levels.
 */
final class DmbcLogger {
	public const string LEVEL_DEBUG = 'DEBUG';
	public const string LEVEL_INFO  = 'INFO';
	public const string LEVEL_WARN  = 'WARN';
	public const string LEVEL_ERROR = 'ERROR';

	private string $channel;
	private bool $enabled;
	private $handler = null;

	/**
	 * Create a logger instance.
	 *
	 * @param string $channel Logical channel for the log output.
	 * @param bool   $enabled Whether logging is enabled.
	 */
	public function __construct( string $channel = 'dmbc-tools', bool $enabled = true ) {
		$this->channel = trim( $channel ) !== '' ? trim( $channel ) : 'dmbc-tools';
		$this->enabled = $enabled;
	}

	/**
	 * Set a custom handler callback for log records.
	 *
	 * @param callable|null $handler Handler invoked with the log entry array.
	 * @return void
	 */
	public function set_handler( ?callable $handler ): void {
		$this->handler = $handler;
	}

	/**
	 * Log a debug record.
	 *
	 * @param string $message Message text.
	 * @param array  $context Structurally formatted values.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Log an info record.
	 *
	 * @param string $message Message text.
	 * @param array  $context Structurally formatted values.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a warning record.
	 *
	 * @param string $message Message text.
	 * @param array  $context Structurally formatted values.
	 * @return void
	 */
	public function warn( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARN, $message, $context );
	}

	/**
	 * Log an error record.
	 *
	 * @param string $message Message text.
	 * @param array  $context Structurally formatted values.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a message at the given level.
	 *
	 * @param string $level Log level.
	 * @param string $message Message text.
	 * @param array  $context Structured context values.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->enabled ) {
			return;
		}

		$normalized_level = strtoupper( trim( $level ) );
		if ( ! in_array( $normalized_level, array( self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARN, self::LEVEL_ERROR ), true ) ) {
			$normalized_level = self::LEVEL_INFO;
		}

		$entry = array(
			'time'    => gmdate( 'c' ),
			'channel' => $this->channel,
			'level'   => $normalized_level,
			'message' => $this->interpolate( $message, $context ),
			'context' => $context,
		);

		if ( is_callable( $this->handler ) ) {
			call_user_func( $this->handler, $entry );
		}

		$this->write_to_wordpress( $entry );
	}

	/**
	 * Interpolate placeholders like {user} with matching context values.
	 *
	 * @param string $message Message template.
	 * @param array  $context Context data.
	 * @return string
	 */
	private function interpolate( string $message, array $context ): string {
		if ( empty( $context ) ) {
			return $message;
		}

		foreach ( $context as $key => $value ) {
			$message = str_replace( '{' . $key . '}', (string) $value, $message );
		}

		return $message;
	}

	/**
	 * Write the entry to WordPress and PHP logging infrastructure.
	 *
	 * @param array $entry Log entry.
	 * @return void
	 */
	private function write_to_wordpress( array $entry ): void {
		$message = sprintf(
			'[%s] [%s] %s',
			$this->channel,
			$entry['level'],
			$entry['message']
		);

		if ( ! empty( $entry['context'] ) ) {
			$message .= ' ' . wp_json_encode( $entry['context'] );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			try {
				wc_get_logger()->log( $this->to_wc_level( $entry['level'] ), $message, array( 'source' => $this->channel ) );
				return;
			} catch ( \Throwable $exception ) {
				// Fall back to PHP error_log and WordPress debug logging.
			}
		}

		if ( function_exists( '\error_log' ) ) {
			\error_log( $message );
		}
	}

	/**
	 * Map internal logger levels to WooCommerce log levels when available.
	 *
	 * @param string $level Internal level.
	 * @return string
	 */
	private function to_wc_level( string $level ): string {
		switch ( $level ) {
			case self::LEVEL_DEBUG:
				return 'debug';
			case self::LEVEL_INFO:
				return 'info';
			case self::LEVEL_WARN:
				return 'warning';
			case self::LEVEL_ERROR:
				return 'error';
			default:
				return 'info';
		}
	}
}
