<?php

use DmbcTools\DmbcSettings;
use DmbcTools\Mailer;
use DmbcTools\Plugin;

/**
 * Tests for the Mailer class.
 */
final class MailerTest extends DmbcUnitTestBase {

	private function make_mailer(): Mailer {
		return new Mailer( new DmbcSettings() );
	}

	/**
	 * No recipients match the requested roles, so no email is sent.
	 *
	 * @covers \DmbcTools\Mailer::send_email
	 */
	public function test_send_email_returns_empty_array_when_no_recipients_match_roles(): void {
		$GLOBALS['dmbc_test_state']['users'] = array();
		$this->set_option( Plugin::OPTION_EMAIL_RECIPIENT, 'admin@example.com' );

		$recipients = $this->make_mailer()->send_email( 'Subject', 'Body', array( 'um_member' ) );

		$this->assertSame( array(), $recipients );
		$this->assertCount( 0, $GLOBALS['dmbc_test_state']['mail_calls'] );
	}

	/**
	 * Recipients match the requested roles, but no email recipient is configured.
	 *
	 * @covers \DmbcTools\Mailer::send_email
	 */
	public function test_send_email_returns_empty_array_when_no_recipient_configured(): void {
		$GLOBALS['dmbc_test_state']['users'] = array(
			(object) array( 'user_email' => 'member@example.com' ),
		);

		$recipients = $this->make_mailer()->send_email( 'Subject', 'Body', array( 'um_member' ) );

		$this->assertSame( array(), $recipients );
		$this->assertCount( 0, $GLOBALS['dmbc_test_state']['mail_calls'] );
	}

	/**
	 * Duplicate and invalid addresses are filtered out of the bcc'd role recipients.
	 *
	 * @covers \DmbcTools\Mailer::send_email
	 */
	public function test_send_email_sends_to_configured_recipient_and_bccs_deduplicated_valid_roles(): void {
		$GLOBALS['dmbc_test_state']['users'] = array(
			(object) array( 'user_email' => 'member@example.com' ),
			(object) array( 'user_email' => 'member@example.com' ),
			(object) array( 'user_email' => 'not-an-email' ),
		);
		$this->set_option( Plugin::OPTION_EMAIL_RECIPIENT, 'admin@example.com' );

		$recipients = $this->make_mailer()->send_email( 'Member Updates', '<p>Body</p>', array( 'um_member' ) );

		$this->assertSame( array( 'member@example.com' ), $recipients );
		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['mail_calls'] );

		$mail_call = $GLOBALS['dmbc_test_state']['mail_calls'][0];
		$this->assertSame( 'admin@example.com', $mail_call['recipients'] );
		$this->assertSame( 'Member Updates', $mail_call['subject'] );
		$this->assertSame( '<p>Body</p>', $mail_call['message'] );
		$this->assertSame(
			array( 'Bcc: member@example.com', 'Content-Type: text/html; charset=UTF-8' ),
			$mail_call['headers']
		);
	}

	/**
	 * Falls back to the site admin email when no recipient option is configured.
	 *
	 * @covers \DmbcTools\Mailer::send_email
	 */
	public function test_send_email_falls_back_to_admin_email_when_no_recipient_configured(): void {
		$GLOBALS['dmbc_test_state']['users'] = array(
			(object) array( 'user_email' => 'member@example.com' ),
		);
		$this->set_option( 'admin_email', 'site-admin@example.com' );

		$recipients = $this->make_mailer()->send_email( 'Subject', 'Body', array( 'um_member' ) );

		$this->assertSame( array( 'member@example.com' ), $recipients );
		$this->assertCount( 1, $GLOBALS['dmbc_test_state']['mail_calls'] );
		$this->assertSame( 'site-admin@example.com', $GLOBALS['dmbc_test_state']['mail_calls'][0]['recipients'] );
	}
}
