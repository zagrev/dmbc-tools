<?php
/**
 * Minimal WordPress function stubs used to unit test plugin classes in
 * isolation. These are intentionally simplified re-implementations of the
 * WordPress behaviors the plugin depends on, not full ports.
 */

$GLOBALS['dmbc_test_state'] = array(
	'options'             => array(),
	'current_user_can'    => true,
	'wp_roles'            => array(),
	'nonce_valid'         => true,
	'registered_settings' => array(),
	'settings_sections'   => array(),
	'settings_fields'     => array(),
);

/** Thrown by the wp_die() stub so tests can assert a fatal-abort occurred. */
class Dmbc_Test_Wp_Die_Exception extends \RuntimeException {}

/** Thrown by the wp_send_json_*() stubs to capture the response that would have been sent. */
class Dmbc_Test_Json_Response_Exception extends \RuntimeException {
	public bool $response_success;
	public $response_data;

	public function __construct( bool $success, $data ) {
		$this->response_success = $success;
		$this->response_data    = $data;
		parent::__construct( $success ? 'wp_send_json_success' : 'wp_send_json_error' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		$options = $GLOBALS['dmbc_test_state']['options'];
		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( string $path ): string {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}
		return '/' === $path ? $path : rtrim( $path, '/' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $email ): string {
		$email = trim( $email );
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}

if ( ! class_exists( 'Dmbc_Test_Wp_Roles' ) ) {
	class Dmbc_Test_Wp_Roles {
		public array $roles;
		public function __construct( array $roles ) {
			$this->roles = $roles;
		}
	}
}

if ( ! function_exists( 'wp_roles' ) ) {
	function wp_roles(): Dmbc_Test_Wp_Roles {
		return new Dmbc_Test_Wp_Roles( $GLOBALS['dmbc_test_state']['wp_roles'] );
	}
}

if ( ! function_exists( 'translate_user_role' ) ) {
	function translate_user_role( string $name ): string {
		return $name;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ): string {
		return addslashes( (string) $text );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $echo = true ): string {
		$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ): string {
		return 'test-nonce';
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'http://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ): void {
		throw new Dmbc_Test_Wp_Die_Exception( is_string( $message ) ? $message : 'wp_die' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return (bool) $GLOBALS['dmbc_test_state']['current_user_can'];
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $query_arg = false, bool $die = true ) {
		if ( ! $GLOBALS['dmbc_test_state']['nonce_valid'] ) {
			if ( $die ) {
				wp_die( 'Invalid nonce' );
			}
			return false;
		}
		return true;
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null ): void {
		throw new Dmbc_Test_Json_Response_Exception( true, $data );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null ): void {
		throw new Dmbc_Test_Json_Response_Exception( false, $data );
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $group, string $name, array $args = array() ): void {
		$GLOBALS['dmbc_test_state']['registered_settings'][ $name ] = array(
			'group' => $group,
			'args'  => $args,
		);
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( string $id, string $title, $callback, string $page ): void {
		$GLOBALS['dmbc_test_state']['settings_sections'][ $id ] = array(
			'title'    => $title,
			'callback' => $callback,
			'page'     => $page,
		);
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( string $id, string $title, $callback, string $page, string $section ): void {
		$GLOBALS['dmbc_test_state']['settings_fields'][ $id ] = array(
			'title'    => $title,
			'callback' => $callback,
			'page'     => $page,
			'section'  => $section,
		);
	}
}

if ( ! function_exists( '__return_empty_string' ) ) {
	function __return_empty_string(): string {
		return '';
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $group ): void {}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	function do_settings_sections( string $page ): void {}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button(): void {}
}
