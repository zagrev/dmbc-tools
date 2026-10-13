<?php

use DmbcTools\DmbcSettings;
use DmbcTools\MailserverProxy;
use DmbcTools\MailserverView;
use DmbcTools\Plugin;

/** @covers \DmbcTools\MailserverProxy */
final class MailserverProxyTest extends DmbcUnitTestBase {

	private function queue_response( int $code, $body ): void {
		$GLOBALS['dmbc_test_state']['remote_responses'][] = array(
			'response' => array( 'code' => $code ),
			'body'     => is_string( $body ) ? $body : json_encode( $body ),
		);
	}

	private function last_request(): array {
		$requests = $GLOBALS['dmbc_test_state']['remote_requests'];
		$this->assertNotEmpty( $requests );
		return end( $requests );
	}

	public function test_routes_mirror_the_gui_api_and_require_administrators(): void {
		$proxy = new MailserverProxy();
		$proxy->register_routes();

		$routes   = $GLOBALS['dmbc_test_state']['rest_routes'];
		$expected = array(
			'dmbc/v1/mailserver/status'                                           => array( 'GET' ),
			'dmbc/v1/mailserver/accounts'                                         => array( 'GET', 'POST' ),
			'dmbc/v1/mailserver/accounts/(?P<email>[^/]+)'                        => array( 'DELETE' ),
			'dmbc/v1/mailserver/accounts/(?P<email>[^/]+)/password'               => array( 'PUT' ),
			'dmbc/v1/mailserver/aliases'                                          => array( 'GET', 'POST' ),
			'dmbc/v1/mailserver/aliases/(?P<source>[^/]+)/(?P<destination>[^/]+)' => array( 'DELETE' ),
		);
		$this->assertSame( array_keys( $expected ), array_keys( $routes ) );
		foreach ( $expected as $route => $methods ) {
			$this->assertSame( $methods, array_column( $routes[ $route ], 'methods' ) );
			foreach ( $routes[ $route ] as $endpoint ) {
				$this->assertSame( array( $proxy, 'can_manage_mailserver' ), $endpoint['permission_callback'] );
			}
		}

		$this->assertTrue( $proxy->can_manage_mailserver() );
		$this->set_current_user_can( false );
		$this->assertFalse( $proxy->can_manage_mailserver() );
	}

	public function test_get_requests_are_forwarded_to_the_configured_gui_api(): void {
		$this->set_option( Plugin::OPTION_MAILSERVER_API_URL, 'http://mail.internal:3001/api/' );
		$accounts = array( array( 'email' => 'dmbc@example.com', 'storage' => array( 'used' => '26M', 'total' => 'unlimited', 'percent' => '0%' ) ) );
		$this->queue_response( 200, $accounts );

		$response = ( new MailserverProxy() )->get_accounts( new WP_REST_Request() );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $accounts, $response->get_data() );
		$request = $this->last_request();
		$this->assertSame( 'http://mail.internal:3001/api/accounts', $request['url'] );
		$this->assertSame( 'GET', $request['args']['method'] );
		$this->assertArrayNotHasKey( 'body', $request['args'] );
	}

	public function test_status_and_aliases_use_default_url(): void {
		$this->queue_response( 200, array( 'status' => 'running' ) );
		$this->queue_response( 200, array() );
		$proxy = new MailserverProxy();

		$this->assertSame( array( 'status' => 'running' ), $proxy->get_status( new WP_REST_Request() )->get_data() );
		$this->assertSame( 'http://localhost:8001/api/status', $GLOBALS['dmbc_test_state']['remote_requests'][0]['url'] );
		$this->assertSame( array(), $proxy->get_aliases( new WP_REST_Request() )->get_data() );
		$this->assertSame( 'http://localhost:8001/api/aliases', $GLOBALS['dmbc_test_state']['remote_requests'][1]['url'] );
	}

	public function test_create_account_forwards_json_body_and_upstream_status(): void {
		$this->queue_response( 201, array( 'message' => 'Account created successfully', 'email' => 'new@example.com' ) );

		$response = ( new MailserverProxy() )->create_account( new WP_REST_Request( array( 'email' => ' New@Example.com ', 'password' => "pa'ss word" ) ) );

		$this->assertSame( 201, $response->get_status() );
		$request = $this->last_request();
		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertSame( 'application/json', $request['args']['headers']['Content-Type'] );
		$this->assertSame( array( 'email' => 'new@example.com', 'password' => "pa'ss word" ), json_decode( $request['args']['body'], true ) );
	}

	public function test_update_password_and_delete_account_encode_the_email_path(): void {
		$proxy = new MailserverProxy();

		$proxy->update_account_password( new WP_REST_Request( array( 'password' => 'secret' ), '', array( 'email' => 'a+b@example.com' ) ) );
		$request = $this->last_request();
		$this->assertSame( 'http://localhost:8001/api/accounts/a%2Bb%40example.com/password', $request['url'] );
		$this->assertSame( 'PUT', $request['args']['method'] );
		$this->assertSame( array( 'password' => 'secret' ), json_decode( $request['args']['body'], true ) );

		$proxy->delete_account( new WP_REST_Request( array(), '', array( 'email' => 'a%2Bb%40example.com' ) ) );
		$request = $this->last_request();
		$this->assertSame( 'http://localhost:8001/api/accounts/a%2Bb%40example.com', $request['url'] );
		$this->assertSame( 'DELETE', $request['args']['method'] );
	}

	public function test_alias_create_and_delete_are_forwarded(): void {
		$proxy = new MailserverProxy();

		$proxy->create_alias( new WP_REST_Request( array( 'source' => 'info@example.com', 'destination' => 'dmbc@example.com' ) ) );
		$request = $this->last_request();
		$this->assertSame( 'http://localhost:8001/api/aliases', $request['url'] );
		$this->assertSame( array( 'source' => 'info@example.com', 'destination' => 'dmbc@example.com' ), json_decode( $request['args']['body'], true ) );

		$proxy->delete_alias( new WP_REST_Request( array(), '', array( 'source' => 'info@example.com', 'destination' => 'dmbc@example.com' ) ) );
		$request = $this->last_request();
		$this->assertSame( 'http://localhost:8001/api/aliases/info%40example.com/dmbc%40example.com', $request['url'] );
		$this->assertSame( 'DELETE', $request['args']['method'] );
	}

	public static function invalid_requests(): array {
		return array(
			'account without email'       => array( 'create_account', array( 'password' => 'x' ), array(), 'dmbc_mailserver_invalid_email' ),
			'account with invalid email'  => array( 'create_account', array( 'email' => 'nope', 'password' => 'x' ), array(), 'dmbc_mailserver_invalid_email' ),
			'account without password'    => array( 'create_account', array( 'email' => 'a@example.com' ), array(), 'dmbc_mailserver_invalid_password' ),
			'password with empty value'   => array( 'update_account_password', array( 'password' => '' ), array( 'email' => 'a@example.com' ), 'dmbc_mailserver_invalid_password' ),
			'delete with shell injection' => array( 'delete_account', array(), array( 'email' => "a@example.com'; rm -rf /" ), 'dmbc_mailserver_invalid_email' ),
			'alias with bad source'       => array( 'create_alias', array( 'source' => 'x', 'destination' => 'a@example.com' ), array(), 'dmbc_mailserver_invalid_source' ),
			'alias with bad destination'  => array( 'delete_alias', array(), array( 'source' => 'a@example.com', 'destination' => 'y' ), 'dmbc_mailserver_invalid_destination' ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalid_requests' )]
	public function test_invalid_input_is_rejected_without_contacting_the_gui( string $method, array $json, array $url, string $code ): void {
		$result = ( new MailserverProxy() )->$method( new WP_REST_Request( $json, '', $url ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $code, $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['remote_requests'] );
	}

	public function test_upstream_errors_are_relayed(): void {
		$this->queue_response( 500, array( 'error' => 'Unable to create account' ) );

		$result = ( new MailserverProxy() )->create_account( new WP_REST_Request( array( 'email' => 'a@example.com', 'password' => 'x' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dmbc_mailserver_error', $result->get_error_code() );
		$this->assertSame( 'Unable to create account', $result->get_error_message() );
		$this->assertSame( array( 'status' => 500 ), $result->get_error_data() );
	}

	public function test_unreachable_and_invalid_responses_return_bad_gateway(): void {
		$GLOBALS['dmbc_test_state']['remote_responses'][] = new WP_Error( 'http_request_failed', 'Connection refused' );
		$this->queue_response( 200, '<html>not json</html>' );
		$this->queue_response( 302, '' );
		$proxy = new MailserverProxy();

		$unreachable = $proxy->get_status( new WP_REST_Request() );
		$this->assertSame( 'dmbc_mailserver_unreachable', $unreachable->get_error_code() );
		$this->assertSame( array( 'status' => 502 ), $unreachable->get_error_data() );

		$invalid = $proxy->get_accounts( new WP_REST_Request() );
		$this->assertSame( 'dmbc_mailserver_invalid_response', $invalid->get_error_code() );
		$this->assertSame( array( 'status' => 502 ), $invalid->get_error_data() );

		$redirect = $proxy->get_aliases( new WP_REST_Request() );
		$this->assertSame( 'dmbc_mailserver_error', $redirect->get_error_code() );
		$this->assertSame( array( 'status' => 502 ), $redirect->get_error_data() );
	}

	public function test_api_url_setting_is_validated(): void {
		$settings = new DmbcSettings();
		$this->set_option( Plugin::OPTION_MAILSERVER_API_URL, 'http://previous:3001/api' );

		$this->assertSame( 'https://mail.example.com/api', $settings->sanitize_mailserver_api_url( ' https://mail.example.com/api/ ' ) );
		$this->assertSame( MailserverProxy::DEFAULT_API_URL, $settings->sanitize_mailserver_api_url( '' ) );
		$this->assertSame( 'http://previous:3001/api', $settings->sanitize_mailserver_api_url( 'file:///etc/passwd' ) );
		$this->assertSame( 'http://previous:3001/api', $settings->sanitize_mailserver_api_url( array( 'x' ) ) );
	}

	/** @covers \DmbcTools\MailserverView::render_page */
	public function test_admin_page_renders_tabs_and_enqueues_the_client(): void {
		ob_start();
		( new MailserverView() )->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'data-tab="dashboard"', $html );
		$this->assertStringContainsString( 'id="dmbc-mailserver-accounts"', $html );
		$this->assertStringContainsString( 'id="dmbc-mailserver-aliases"', $html );
		$this->assertStringContainsString( 'http://localhost:8001/api', $html );
		$this->assertSame( array( 'wp-api-fetch' ), $GLOBALS['dmbc_test_state']['enqueued_scripts']['dmbc-mailserver']['deps'] );
		$this->assertSame( array( 'path' => '/dmbc/v1/mailserver' ), $GLOBALS['dmbc_test_state']['localized_scripts']['dmbc-mailserver']['dmbcMailserver'] );
	}

	/** @covers \DmbcTools\MailserverView::render_page */
	public function test_admin_page_rejects_non_administrators(): void {
		$this->set_current_user_can( false );
		$this->expectException( Dmbc_Test_Wp_Die_Exception::class );

		( new MailserverView() )->render_page();
	}
}
