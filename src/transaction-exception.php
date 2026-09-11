<?php
/**
 * Mailer class file.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

use Exception;
use Throwable;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * Transaction Exception for DMBC Credit Card transactions
 */
class TransactionException extends Exception {
	/**
	 * Override constructor to provide a default message or custom logic
	 *
	 * @param string         $message The exception message.
	 * @param int            $code The exception code.
	 * @param Throwable|null $previous The previous throwable used for exception chaining.
	 */
	public function __construct( string $message = 'Database connection failed', int $code = 422, Throwable|null $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}
}
