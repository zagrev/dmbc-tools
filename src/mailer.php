<?php
/**
 * Mailer class file.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * Sends plugin emails through wp_mail().
 */
class Mailer {
	/**
	 * The settings used to determine recipients.
	 *
	 * @var DmbcSettings
	 */
	private DmbcSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param DmbcSettings $settings The plugin settings.
	 */
	public function __construct( DmbcSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Send an email to all users with the specified roles, bcc'ing them.
	 *
	 * @param string        $subject The email subject.
	 * @param string        $message The email body.
	 * @param array<string> $roles   The roles of users to email.
	 * @return array<string> The recipients who were emailed.
	 */
	public function send_email( string $subject, string $message, array $roles ): array {
		$recipients = array_values(
			array_unique(
				array_filter(
					array_map(
						fn( $user ) => isset( $user->user_email ) ? $user->user_email : '',
						(array) \get_users( array( 'role__in' => $roles ) )
					),
					fn( string $email ): bool => \is_email( $email ) !== false
				)
			)
		);

		if ( empty( $recipients ) ) {
			return array();
		}

		$recipient = $this->settings->get_email_recipient();
		if ( empty( $recipient ) ) {
			return array();
		}

		foreach ( array_chunk( $recipients, $this->settings->get_max_bcc_per_email() ) as $bcc_batch ) {
			$headers = array(
				'Bcc: ' . implode( ',', $bcc_batch ),
				'Content-Type: text/html; charset=UTF-8',
			);

			\wp_mail( $recipient, $subject, $message, $headers );
		}

		return $recipients;
	}
}
