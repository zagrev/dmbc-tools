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
	 * @param string             $subject The email subject.
	 * @param string             $message The email body.
	 * @param array<string>|null $roles   The roles of users to email.
	 * @return array<string> The recipients who were emailed.
	 */
	public function send_email( string $subject, string $message, array|null $roles = null ): array {
		$roles      = null === $roles ? $this->settings->get_song_list_recipient_roles() : (array) $roles;
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

		// This can never return empty, so no need to check the result.
		$recipient = $this->settings->get_email_recipient();

		foreach ( array_chunk( $recipients, $this->settings->get_max_bcc_per_email() ) as $bcc_batch ) {
			$headers = array(
				'Bcc: ' . implode( ',', $bcc_batch ),
				'Content-Type: text/html; charset=UTF-8',
			);

			\wp_mail( $recipient, $subject, $message, $headers );
		}

		return $recipients;
	}


	/**
	 *
	 * Create an email from the given template and send it
	 * to the
	 * customer email and copy the
	 * default email address.
	 *
	 * @param string $template_name The name of the email template to use.
	 * @param array  $ticket_data The data related to the ticket purchase to be used in the email template.
	 * @return void
	 */
	public function send_email_using_template( string $template_name, array $ticket_data ) {
		$template = \get_posts(
			array(
				'title'       => $template_name,
				'post_type'   => 'post',
				'numberposts' => 1,
			)
		);
		if ( false === $template || empty( $template ) ) {
			\error_log( 'Email template not found: ' . $template_name );
			return;
		}

		$to = $ticket_data['email'];
		if ( empty( $to ) ) {
			\error_log( 'No recipient email found in ticket data.' );
			return;
		}

		// Format the post so we can send it as HTML.
		$template_post = $template[0];
		$subject       = get_the_title( $template_post );
		$message       = apply_filters( 'the_content', $template_post->post_content );

		// Find the line item template within the message and replace it for each line item.
		$line_item_regex = '/\{\{ItemList\}\}(.*)\{\{\/ItemList\}\}/';

		$items              = $ticket_data['items'] ?? array();
		$line_item_template = preg_match( $line_item_regex, $message, $matches ) ? $matches[1] : '';

		$rows = array();
		foreach ( $items as &$item ) {
			$rows[] = str_replace(
				array( '{{ItemName}}', '{{ItemQuantity}}', '{{ItemPrice}}' ),
				array( $item['type'] ?? '', $item['count'] ?? '', $item['total'] ?? '' ),
				$line_item_template
			);
		}
		$message = preg_replace( $line_item_regex, implode( '', $rows ), $message );

		// Replace non-repeating placeholders in the message with actual ticket data.
		$message = str_replace(
			array( '{{CustomerName}}', '{{PurchaseAmount}}', '{{PurchaseDate}}' ),
			array( $ticket_data['name'] ?? '', $ticket_data['total'] ?? '', $ticket_data['tx_date'] ?? '' ),
			$message
		);
		\error_log( 'After processing line items: ' . $message );

		$cc = 'Cc: ' . $this->settings->get_email_recipient();
		\error_log( 'CC: ' . $cc );
		if ( ! \wp_mail( $to, $subject, $message, array( $cc, 'Content-Type: text/html; charset=UTF-8' ) ) ) {
			\error_log( 'Failed to send ticket purchase ($transaction_id: ' . ( $ticket_data['transaction_id'] ?? '' ) . ') confirmation email to: ' . $to );
		}
	}
}
