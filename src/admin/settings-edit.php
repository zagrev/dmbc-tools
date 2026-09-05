<?php
/**
 * Render settings for the DMBC Tools plugin.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

/**
 * Summary of DmbcSettings class.
 */
class DmbcSettings {
	/**
	 * Registers the settings for this plugin.
	 */
	public function register_settings(): void {
		\error_log( 'DMBC Settings: register_settings method called.' );

		register_setting(
			'settings_group',
			'song_library_directory',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_song_library_directory' ),
				'default'           => 'dmbc-song-library',
			)
		);
		register_setting(
			'settings_group',
			'song_library_exclusion_regexes',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_song_library_exclusion_regexes' ),
				'default'           => array(),
			)
		);
		register_setting(
			'settings_group',
			'song_list_recipient_roles',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_song_list_recipient_roles' ),
				'default'           => array(),
			)
		);
		register_setting(
			'settings_group',
			'song_list_default_recipient',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_song_list_default_recipient' ),
				'default'           => '',
			)
		);
		register_setting(
			'settings_group',
			'member_update_recipient',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_member_update_recipient' ),
				'default'           => '',
			)
		);

		add_settings_section(
			'general_section',
			__( 'General', 'dmbc-tools' ),
			'__return_empty_string',
			'settings'
		);
		add_settings_field(
			'song_library_directory',
			__( 'Song library directory', 'dmbc-tools' ),
			array( $this, 'render_song_library_directory_field' ),
			'settings',
			'general_section'
		);
		add_settings_field(
			'song_library_exclusion_regexes',
			__( 'Song library exclusion regexes', 'dmbc-tools' ),
			array( $this, 'render_song_library_exclusion_regexes_field' ),
			'settings',
			'general_section'
		);
		add_settings_section(
			'notifications_section',
			__( 'Rehearsal song list notifications', 'dmbc-tools' ),
			'__return_empty_string',
			'settings'
		);
		add_settings_field(
			'song_list_recipient_roles',
			__( 'Recipient roles', 'dmbc-tools' ),
			array( $this, 'render_song_list_recipient_roles_field' ),
			'settings',
			'notifications_section'
		);
		add_settings_field(
			'song_list_default_recipient',
			__( 'Default recipient', 'dmbc-tools' ),
			array( $this, 'render_song_list_default_recipient_field' ),
			'settings',
			'notifications_section'
		);
		add_settings_section(
			'member_update_notifications_section',
			__( 'Member update notifications', 'dmbc-tools' ),
			'__return_empty_string',
			'settings'
		);
		add_settings_field(
			'member_update_recipient',
			__( 'Primary recipient', 'dmbc-tools' ),
			array( $this, 'render_member_update_recipient_field' ),
			'settings',
			'member_update_notifications_section'
		);
	}

		/** Render the song library directory field. */
	public function render_song_library_directory_field(): void {
		$value = $this->get_song_library_directory_option();
		$nonce = \wp_create_nonce( 'dmbc_browse_directory' );
		?>
		<div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
		<input type="text" name="song_library_directory" id="song_library_directory" value="<?php echo \esc_attr( $value ); ?>"
			class="regular-text" placeholder="/path/to/song-library" />
		<button type="button" class="button" id="apply_folder_selection" data-nonce="<?php echo \esc_attr( $nonce ); ?>">
		<?php \esc_html_e( 'Browse...', 'dmbc-tools' ); ?>
		</button>
		</div>
		<p class="description">
		<?php \esc_html_e( 'Choose a server directory for the song library. Click the button to browse the server filesystem, or enter a path manually.', 'dmbc-tools' ); ?>
		</p>

		<div id="dmbc_folder_browser_modal" style="display:none; position:fixed; z-index:100000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
			<div style="background:#fff; max-width:600px; margin:60px auto; padding:20px; border-radius:4px; max-height:70vh; display:flex; flex-direction:column;">
				<h2 style="margin-top:0;"><?php \esc_html_e( 'Select a folder', 'dmbc-tools' ); ?></h2>
				<p><strong><?php \esc_html_e( 'Current path:', 'dmbc-tools' ); ?></strong> <span id="dmbc_folder_browser_current_path"></span></p>
				<div id="dmbc_folder_browser_message" style="color:#a00; margin-bottom:8px;"></div>
				<div style="flex:1; overflow-y:auto; border:1px solid #ccc; padding:8px;">
					<ul id="dmbc_folder_browser_list" style="margin:0; list-style:none; padding-left:0;"></ul>
				</div>
				<p style="margin-top:12px; text-align:right;">
					<button type="button" class="button" id="dmbc_folder_browser_up"><?php \esc_html_e( 'Up one level', 'dmbc-tools' ); ?></button>
					<button type="button" class="button" id="dmbc_folder_browser_cancel"><?php \esc_html_e( 'Cancel', 'dmbc-tools' ); ?></button>
					<button type="button" class="button button-primary" id="dmbc_folder_browser_select"><?php \esc_html_e( 'Select this folder', 'dmbc-tools' ); ?></button>
				</p>
			</div>
		</div>

		<script>
		jQuery(function ($) {
			var ajaxUrl     = <?php echo \wp_json_encode( \admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce       = $('#apply_folder_selection').data('nonce');
			var currentPath = null;

			function loadDirectory(path) {
				$('#dmbc_folder_browser_message').text('');
				$.post(ajaxUrl, {
					action: 'dmbc_browse_directory',
					nonce: nonce,
					path: path || ''
				}).done(function (response) {
					if (!response.success) {
						$('#dmbc_folder_browser_message').text(response.data && response.data.message ? response.data.message : <?php echo \wp_json_encode( \__( 'Unable to browse this directory.', 'dmbc-tools' ) ); ?>);
						return;
					}
					currentPath = response.data.path;
					$('#dmbc_folder_browser_current_path').text(currentPath);
					$('#dmbc_folder_browser_up').data('parent', response.data.parent || '');
					$('#dmbc_folder_browser_up').prop('disabled', !response.data.parent);

					var $list = $('#dmbc_folder_browser_list').empty();
					if (response.data.directories.length === 0) {
						$list.append($('<li>').css('color', '#666').text(<?php echo \wp_json_encode( \__( 'No subfolders', 'dmbc-tools' ) ); ?>));
					}
					$.each(response.data.directories, function (i, name) {
						var $link = $('<a href="#" class="dmbc-folder-entry"></a>').text(name).data('name', name);
						$list.append($('<li>').append($link));
					});
				}).fail(function () {
					$('#dmbc_folder_browser_message').text(<?php echo \wp_json_encode( \__( 'Unable to reach the server.', 'dmbc-tools' ) ); ?>);
				});
			}

			$('#apply_folder_selection').on('click', function () {
				$('#dmbc_folder_browser_modal').show();
				loadDirectory($('#song_library_directory').val());
			});

			$('#dmbc_folder_browser_list').on('click', '.dmbc-folder-entry', function (e) {
				e.preventDefault();
				loadDirectory(currentPath.replace(/\/$/, '') + '/' + $(this).data('name'));
			});

			$('#dmbc_folder_browser_up').on('click', function () {
				var parent = $(this).data('parent');
				if (parent) {
					loadDirectory(parent);
				}
			});

			$('#dmbc_folder_browser_cancel').on('click', function () {
				$('#dmbc_folder_browser_modal').hide();
			});

			$('#dmbc_folder_browser_select').on('click', function () {
				if (currentPath) {
					$relative_path = currentPath.split('/').pop();
					$('#song_library_directory').val($relative_path);
				}
				$('#dmbc_folder_browser_modal').hide();
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler that lists the subdirectories of a given server path so the
	 * admin can browse the filesystem when choosing the song library directory.
	 */
	public function ajax_browse_directory(): void {
		\check_ajax_referer( 'dmbc_browse_directory', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'You do not have permission to browse the server filesystem.', 'dmbc-tools' ) ), 403 );
		}

		$requested_path = isset( $_POST['path'] ) ? (string) \sanitize_file_name( \wp_unslash( $_POST['path'] ) ) : '';
		// if not an absolute path, prepend with WP_CONTENT_DIR, else use as is.
		if ( ! preg_match( '#^([a-zA-Z]:)?/#', $requested_path ) ) {
			$requested_path = WP_CONTENT_DIR . '/' . $requested_path;
		}

		$real_path = realpath( $requested_path );

		if ( false === $real_path || ! is_dir( $real_path ) || ! is_readable( $real_path ) ) {
			\wp_send_json_error( array( 'message' => \__( 'The requested directory could not be found or is not readable.', 'dmbc-tools' ) ), 400 );
		}

		$current_path = \wp_normalize_path( $real_path );
		$entries      = @scandir( $current_path );

		if ( false === $entries ) {
			\wp_send_json_error( array( 'message' => \__( 'Unable to read the requested directory.', 'dmbc-tools' ) ), 400 );
		}

		$directories = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			if ( is_dir( $current_path . '/' . $entry ) ) {
				$directories[] = $entry;
			}
		}

		natcasesort( $directories );

		$parent_path = \wp_normalize_path( \dirname( $current_path ) );

		\wp_send_json_success(
			array(
				'path'        => $current_path,
				'parent'      => $parent_path !== $current_path ? $parent_path : null,
				'directories' => array_values( $directories ),
			)
		);
	}

	/**
	 * Render the song library exclusion regexes field.
	 */
	public function render_song_library_exclusion_regexes_field(): void {
		$value = implode( "\n", $this->get_song_library_exclusion_regexes() );
		?>
		<textarea name="song_library_exclusion_regexes" id="song_library_exclusion_regexes" rows="5"
			class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Enter one regular expression per line. Do not include the delimiters (leading and trailing slashes). Matching song folders are excluded from the song selector.', 'dmbc-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the song list recipient roles field.
	 */
	public function render_song_list_recipient_roles_field(): void {
		$selected_roles = $this->get_song_list_recipient_roles();
		$roles          = \wp_roles()->roles;
		foreach ( $roles as $role_slug => $role ) {
			$role_name = \translate_user_role( $role['name'] );
			?>
			<label>
				<input type="checkbox" name="song_list_recipient_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $selected_roles, true ) ); ?> />
				<?php echo esc_html( $role_name ); ?>
			</label><br />
			<?php
		}
		?>
		<p class="description">
			<?php \esc_html_e( 'Users with these roles will receive an email when a rehearsal song list is created or updated.', 'dmbc-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the song list default recipient field.
	 */
	public function render_song_list_default_recipient_field(): void {
		?>
		<input type="email" name="song_list_default_recipient" id="song_list_default_recipient" value="<?php echo \esc_attr( $this->get_song_list_default_recipient() ); ?>"
			class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'This address receives the email in addition to selected role members. It defaults to the site administrator email.', 'dmbc-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the primary recipient field for member update digests.
	 *
	 * @return void
	 */
	public function render_member_update_recipient_field(): void {
		?>
		<input type="email" name="member_update_recipient" id="member_update_recipient" value="<?php echo \esc_attr( $this->get_member_update_recipient() ); ?>"
			class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'This address receives member update digests directly. Members are included as BCC recipients. It defaults to the site administrator email.', 'dmbc-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the settings page only if the user can view/edit the settings
	 *
	 * @return void
	 */
	public function dmbc_render_settings_page(): void {
		if ( ! \current_user_can( 'manage_options' ) && ! \current_user_can( Plugin::CAP_EDIT_SONGLIST ) ) {
			\wp_die( \esc_html__( 'You do not have permission to access this page.', 'dmbc-tools' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DMBC Tools Settings', 'dmbc-tools' ); ?></h1>
			<form method="post" action="options.php">
			<?php
			settings_fields( 'settings_group' );
			do_settings_sections( 'settings' );
			submit_button();
			?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize the song library directory path. This removes any trailing slashes and normalizes the directory separators.
	 *
	 * @param string $value the folder path to sanitize.
	 * @return string
	 */
	public function sanitize_song_library_directory( string $value ) {
		$value = trim( (string) $value );
		$value = str_replace( '\\', '/', $value );
		$value = rtrim( $value, '/' );

		return $value;
	}

	/**
	 * Get the song library diretory setting.
	 *
	 * @return string the sanitized song library directory option, or 'dmbc-song-library' if not set.
	 */
	public function get_song_library_directory_option() {
		return $this->sanitize_song_library_directory(
			(string) \get_option( 'song_library_directory', 'dmbc-song-library' )
		);
	}

	/**
	 * Get the song library path in the WordPress installation.
	 *
	 * @return string the sanitized song library directory path.
	 */
	public function get_song_library_directory_path() {
		$directory = $this->get_song_library_directory_option();

		if ( empty( $directory ) ) {
			$directory = 'dmbc-song-library';
		}

		// if already an absolute path, return it as-is.
		if ( preg_match( '#^([a-zA-Z]:)?/#', $directory ) ) {
			return wp_normalize_path( $directory );
		}
		return \wp_normalize_path( WP_CONTENT_DIR . '/' . $directory );
	}

	/**
	 * Sanitize the regular expressions to exclude from the song library.
	 *
	 * @param string|array $value the regular expressions to sanitize.
	 * @return array
	 */
	public function sanitize_song_library_exclusion_regexes( string|array $value ) {
		$regexes = \is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		$regexes = \array_map( 'trim', $regexes ? $regexes : array() );

		return array_values(
			array_filter(
				$regexes,
				fn( $regex ) => '' !== $regex && false !== @preg_match( '/' . $regex . '/', '' )
			)
		);
	}

	/**
	 * Retrieve the array of regular expresessions to exlude from the song list.
	 *
	 * @return array
	 */
	public function get_song_library_exclusion_regexes() {
		return $this->sanitize_song_library_exclusion_regexes( \get_option( 'song_library_exclusion_regexes', array() ) );
	}

	/**
	 * Sanitize the roles of users to receive the song list update email.
	 *
	 * @param string|array $value the roles to sanitize.
	 * @return array
	 */
	public function sanitize_song_list_recipient_roles( string|array $value ) {
		$value = is_array( $value ) ? $value : array();
		$roles = function_exists( 'wp_roles' ) ? array_keys( \wp_roles()->roles ) : array();
		$value = array_map(
			fn ( $role ) => function_exists( 'sanitize_key' ) ? \sanitize_key( $role ) : (string) $role,
			$value
		);

		return array_values( array_intersect( $value, $roles ) );
	}

	/**
	 * Retrieve the list of recipient roles for the song list update email.
	 *
	 * @return array
	 */
	public function get_song_list_recipient_roles() {
		$roles = \get_option( 'song_list_recipient_roles', array() );

		return is_array( $roles ) ? $this->sanitize_song_list_recipient_roles( $roles ) : array();
	}

	/**
	 *  Sanitize the list of default email recipients
	 *
	 * @param mixed $value the default email recipient to sanitize.
	 * @return string
	 */
	public function sanitize_song_list_default_recipient( $value ) {
		return function_exists( 'sanitize_email' ) ? \sanitize_email( $value ) : '';
	}

	/**
	 *  Retrieve the default email recipient for the song list updates.
	 *
	 * @return string
	 */
	public function get_song_list_default_recipient() {
		$recipient = \get_option( 'song_list_default_recipient', '' );

		if ( empty( $recipient ) ) {
			$recipient = \get_option( 'admin_email', '' );
		}

		return $this->sanitize_song_list_default_recipient( $recipient );
	}

	/**
	 * Sanitize the primary recipient for member update digests.
	 *
	 * @param mixed $value The email address to sanitize.
	 * @return string
	 */
	public function sanitize_member_update_recipient( $value ): string {
		return function_exists( 'sanitize_email' ) ? \sanitize_email( (string) $value ) : '';
	}

	/**
	 * Retrieve the primary recipient for member update digests.
	 *
	 * @return string
	 */
	public function get_member_update_recipient(): string {
		$recipient = \get_option( 'member_update_recipient', '' );
		if ( empty( $recipient ) ) {
			$recipient = \get_option( 'admin_email', '' );
		}

		return $this->sanitize_member_update_recipient( $recipient );
	}

	/**
	 * Iterate of the song library and return the relative name of each folder found.
	 *
	 * @param string|null $base_directory The root directory that contains a list of all the songs folders.
	 * @return array
	 */
	public function get_wp_content_folder_choices( string|null $base_directory = null ) {
		$normalized_base_directory = $base_directory ? \wp_normalize_path( $base_directory ) : $this->get_song_library_directory_path();

		if ( ! is_dir( $normalized_base_directory ) ) {
			return array();
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $normalized_base_directory, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		$choices = array();
		foreach ( $iterator as $path ) {
			if ( ! $path->isDir() ) {
				continue;
			}

			$normalized_path = str_replace( '\\', '/', $path->getPathname() );
			$relative_path   = trim( str_replace( $normalized_base_directory . '/', '', $normalized_path ), '/' );

			if ( empty( $relative_path ) ) {
				continue;
			}

			$choices[ $normalized_path ] = $relative_path;
		}

		ksort( $choices, SORT_NATURAL | SORT_FLAG_CASE );

		return $choices;
	}
}
