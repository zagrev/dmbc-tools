<?php
/**
 * REST pass-through to the Docker Mailserver GUI backend.
 *
 * The Docker Mailserver GUI backend (https://github.com/dunaj-dev/docker-mailserver-GUI)
 * executes `setup` commands inside the docker-mailserver container. These routes mirror
 * its API so WordPress administrators can manage mail accounts and aliases from wp-admin.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WordPress REST routes that forward requests to the Docker Mailserver GUI API.
 */
class MailserverProxy {
	public const string REST_NAMESPACE  = 'dmbc/v1';
	public const string REST_BASE       = '/mailserver';
	public const string DEFAULT_API_URL = 'http://localhost:8001/api';
	public const string CAPABILITY      = 'manage_options';
	public const int TIMEOUT_SECONDS    = 30;

	/**
	 * Register the mail server REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$base   = self::REST_BASE;
		$email  = '(?P<email>[^/]+)';
		$routes = array(
			'/status'                     => array(
				array( 'GET', 'get_status' ),
			),
			'/accounts'                   => array(
				array( 'GET', 'get_accounts' ),
				array( 'POST', 'create_account' ),
			),
			"/accounts/{$email}"          => array(
				array( 'DELETE', 'delete_account' ),
			),
			"/accounts/{$email}/password" => array(
				array( 'PUT', 'update_account_password' ),
			),
			'/aliases'                    => array(
				array( 'GET', 'get_aliases' ),
				array( 'POST', 'create_alias' ),
			),
			'/aliases/(?P<source>[^/]+)/(?P<destination>[^/]+)' => array(
				array( 'DELETE', 'delete_alias' ),
			),
		);

		foreach ( $routes as $route => $endpoints ) {
			$args = array();
			foreach ( $endpoints as list( $method, $callback ) ) {
				$args[] = array(
					'methods'             => $method,
					'callback'            => array( $this, $callback ),
					'permission_callback' => array( $this, 'can_manage_mailserver' ),
				);
			}
			\register_rest_route( self::REST_NAMESPACE, $base . $route, $args );
		}
	}

	/**
	 * Only administrators may manage the mail server.
	 *
	 * @return bool
	 */
	public function can_manage_mailserver(): bool {
		return \current_user_can( self::CAPABILITY );
	}

	/**
	 * Return the configured base URL of the Docker Mailserver GUI API.
	 *
	 * @return string
	 */
	public static function get_api_url(): string {
		$url = \get_option( Plugin::OPTION_MAILSERVER_API_URL, self::DEFAULT_API_URL );
		$url = is_string( $url ) ? trim( $url ) : '';
		return rtrim( '' === $url ? self::DEFAULT_API_URL : $url, '/' );
	}

	/**
	 * Container status and resource usage.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_status() {
		return $this->forward( 'GET', '/status' );
	}

	/**
	 * List email accounts.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_accounts() {
		return $this->forward( 'GET', '/accounts' );
	}

	/**
	 * Create an email account.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_account( \WP_REST_Request $request ) {
		$email    = $this->email_param( $request, 'email' );
		$password = $this->password_param( $request );
		if ( \is_wp_error( $email ) ) {
			return $email;
		}
		if ( \is_wp_error( $password ) ) {
			return $password;
		}
		return $this->forward(
			'POST',
			'/accounts',
			array(
				'email'    => $email,
				'password' => $password,
			)
		);
	}

	/**
	 * Change an email account's password.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_account_password( \WP_REST_Request $request ) {
		$email    = $this->email_param( $request, 'email' );
		$password = $this->password_param( $request );
		if ( \is_wp_error( $email ) ) {
			return $email;
		}
		if ( \is_wp_error( $password ) ) {
			return $password;
		}
		return $this->forward( 'PUT', '/accounts/' . rawurlencode( $email ) . '/password', array( 'password' => $password ) );
	}

	/**
	 * Delete an email account.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_account( \WP_REST_Request $request ) {
		$email = $this->email_param( $request, 'email' );
		if ( \is_wp_error( $email ) ) {
			return $email;
		}
		return $this->forward( 'DELETE', '/accounts/' . rawurlencode( $email ) );
	}

	/**
	 * List aliases.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_aliases() {
		return $this->forward( 'GET', '/aliases' );
	}

	/**
	 * Create an alias.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_alias( \WP_REST_Request $request ) {
		$source      = $this->email_param( $request, 'source' );
		$destination = $this->email_param( $request, 'destination' );
		if ( \is_wp_error( $source ) ) {
			return $source;
		}
		if ( \is_wp_error( $destination ) ) {
			return $destination;
		}
		return $this->forward(
			'POST',
			'/aliases',
			array(
				'source'      => $source,
				'destination' => $destination,
			)
		);
	}

	/**
	 * Delete an alias.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_alias( \WP_REST_Request $request ) {
		$source      = $this->email_param( $request, 'source' );
		$destination = $this->email_param( $request, 'destination' );
		if ( \is_wp_error( $source ) ) {
			return $source;
		}
		if ( \is_wp_error( $destination ) ) {
			return $destination;
		}
		return $this->forward( 'DELETE', '/aliases/' . rawurlencode( $source ) . '/' . rawurlencode( $destination ) );
	}

	/**
	 * Read and validate an email address parameter.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @param string           $name The parameter name.
	 * @return string|\WP_Error
	 */
	private function email_param( \WP_REST_Request $request, string $name ) {
		$value = $request->get_param( $name );
		$value = is_string( $value ) ? strtolower( trim( rawurldecode( $value ) ) ) : '';
		if ( '' === $value || ! \is_email( $value ) ) {
			return new \WP_Error(
				'dmbc_mailserver_invalid_' . $name,
				/* translators: %s: parameter name, e.g. email, source, destination. */
				sprintf( \__( 'A valid %s email address is required.', 'dmbc-tools' ), $name ),
				array( 'status' => 400 )
			);
		}
		return $value;
	}

	/**
	 * Read and validate the password parameter.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return string|\WP_Error
	 */
	private function password_param( \WP_REST_Request $request ) {
		$value = $request->get_param( 'password' );
		if ( ! is_string( $value ) || '' === $value ) {
			return new \WP_Error( 'dmbc_mailserver_invalid_password', \__( 'A password is required.', 'dmbc-tools' ), array( 'status' => 400 ) );
		}
		return $value;
	}

	/**
	 * Forward a request to the Docker Mailserver GUI API and relay its JSON response.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path Path relative to the API base URL.
	 * @param array|null $body JSON body to send.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function forward( string $method, string $path, ?array $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT_SECONDS,
			'headers' => array( 'Accept' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = \wp_json_encode( $body );
		}

		$response = \wp_remote_request( self::get_api_url() . $path, $args );
		if ( \is_wp_error( $response ) ) {
			Plugin::instance()->logger()->error( "Mail server request {$method} {$path} failed: " . $response->get_error_message() );
			return new \WP_Error( 'dmbc_mailserver_unreachable', \__( 'Unable to reach the Docker Mailserver GUI.', 'dmbc-tools' ), array( 'status' => 502 ) );
		}

		$status = (int) \wp_remote_retrieve_response_code( $response );
		$raw    = (string) \wp_remote_retrieve_body( $response );
		$data   = '' === $raw ? null : json_decode( $raw, true );
		if ( '' !== $raw && JSON_ERROR_NONE !== json_last_error() ) {
			Plugin::instance()->logger()->error( "Mail server request {$method} {$path} returned non-JSON (HTTP {$status})." );
			return new \WP_Error( 'dmbc_mailserver_invalid_response', \__( 'The Docker Mailserver GUI returned an invalid response.', 'dmbc-tools' ), array( 'status' => 502 ) );
		}

		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] )
				? $data['error']
				: \__( 'The Docker Mailserver GUI request failed.', 'dmbc-tools' );
			Plugin::instance()->logger()->error( "Mail server request {$method} {$path} failed (HTTP {$status}): {$message}" );
			return new \WP_Error( 'dmbc_mailserver_error', $message, array( 'status' => $status >= 400 && $status < 600 ? $status : 502 ) );
		}

		return new \WP_REST_Response( $data, $status );
	}
}
