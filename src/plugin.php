<?php
declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined . This file( ' . __FILE__ . ' ) should not be accessed directly . ' . PHP_EOL;
	exit;
}

require_once __DIR__ . '/admin/settings-edit.php';
require_once __DIR__ . '/admin/menu.php';
require_once __DIR__ . '/songlist.php';

use DmbcTools\SongListView;
use DmbcTools\DmbcSettings;

/**
 * The DMBC Plugin
 */
final class Plugin {
	public const string VERSION                   = '0.1.0';
	public const string OPTION_VERSION            = 'dmbc_tools_version';
	public const string SONGLIST_POST_TYPE        = 'dmbc-songlist';
	public const string MEMBER_UPDATE_POST_TYPE   = 'dmbc-member-updates';
	public const string SONGLIST_META_NONCE       = 'dmbc_songlist_meta_nonce';
	public const string PERFORMANCE_DATE_META_KEY = '_dmbc_performance_date';
	public const string SONGS_META_KEY            = '_dmbc_songs';
	public const string NOTES_META_KEY            = '_dmbc_notes';

	public const string CAP_EDIT_SONGLIST  = 'dmbc_edit_songlist';
	public const string CAP_VIEW_SONGLISTS = 'dmbc_view_songlist';
	public const string CAP_EDIT_MEMBER_UPDATES = 'dmbc_edit_member_updates';
	public const string CAP_VIEW_MEMBER_UPDATES = 'dmbc_view_member_updates';
	public const string CAP_PUBLISH_MEMBER_UPDATES = 'dmbc_publish_member_updates';

	/**
	 *  The settings used by the plugin.
	 *
	 * @var DmbcSettings
	 */
	private DmbcSettings $settings;

	/**
	 * The song list view handler.
	 *
	 * @var SongListView
	 */
	private SongListView $song_list_view;

	/**
	 * The member update view handler.
	 *
	 * @var MemberUpdateView
	 */
	private MemberUpdateView $member_update_view;

	/**
	 * The singleton instance of this plugin
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		\error_log( 'DMBC Plugin: constructor called . ' );

		$this->settings = new DmbcSettings();
	}

	/**
	 * Get the singleton instance of this plugin.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the hooks that start the plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		\error_log( 'DMBC Plugin: "run" called. ------------------------------------' );
		// \add_action( 'all', fn( $tag ) => \error_log( $tag . ': ' . \print( \func_get_args(), true ) ) );

		\add_action( 'init', array( $this, 'initialize' ) );
		\add_action( 'admin_init', array( $this, 'handle_admin_init' ) );
		\add_action( 'admin_menu', array( $this, 'register_admin' ) );
		\add_action( 'add_meta_boxes', array( $this, 'add_songlist_meta_box' ) );
		\add_action( 'save_post_' . self::SONGLIST_POST_TYPE, array( $this, 'save_songlist_meta' ) );

		\add_action( 'wp_dashboard_setup', array( $this, 'register_user_capabilities_dashboard_widget' ) );
		\add_action( 'wp_ajax_dmbc_browse_directory', array( $this->settings, 'ajax_browse_directory' ) );

		\flush_rewrite_rules();
	}

	// init -------------------------------------------------------------------------------

	/**
	 * Set up everyting for the Plugin. This method is called at 'init' time.
	 *
	 * @return void
	 */
	public function initialize(): void {
		\error_log( 'DMBC Plugin: "initialize" called.' );

		$this->register_songlist_type();
		$this->register_member_update_type();
		$this->register_options();
		$this->add_songlist_capabilities();

		\flush_rewrite_rules();
	}

	/**
	 * Handle submitted song-list forms before the admin menu is rendered.
	 *
	 * @return void
	 */
	public function handle_admin_init(): void {
		$this->create_song_list_view();
		$this->song_list_view->handle_song_list_form();
	}

	/**
	 * Register the custom dashboard widget.
	 */
	public function register_user_capabilities_dashboard_widget() {
		\error_log( 'DMBC Tools: registering user capabilities dashboard widget . ' );
		wp_add_dashboard_widget(
			'wp_user_capabilities_widget',
			'Your Current Capabilities',
			array( $this, 'render_user_capabilities_widget' )
		);
	}

	/**
	 * Display the current user's capabilities inside the widget.
	 *
	 * @return void
	 */
	public function render_user_capabilities_widget() {
		\error_log( 'DMBC Tools: displaying user capabilities widget.' );
		// Get the current user data object.
		$current_user = wp_get_current_user();

		if ( ! $current_user->ID ) {
			echo '<p>No user logged in.</p>';
			return;
		}

		// Retrieve all capabilities assigned directly or via roles.
		$all_caps = $current_user->allcaps;

		echo '<p><strong>Username:</strong> ' . esc_html( $current_user->user_login ) . '</p>';
		echo '<p><strong>Primary Roles:</strong> ' . esc_html( implode( ', ', $current_user->roles ) ) . '</p>';
		echo '<hr />';
		echo '<p><strong>Assigned Capabilities:</strong></p>';

		if ( ! empty( $all_caps ) ) {
			// Filter out capabilities that are explicitly set to false.
			$active_caps = array_filter( $all_caps );

			echo '<div style="max-height: 250px; overflow-y: auto; padding: 5px; background: #f6f7f7; border: 1px solid #dcdcde;">';
			echo '<ul style="margin: 0; padding-left: 20px; list-style-type: disc;">';
			foreach ( array_keys( $active_caps ) as $cap ) {
				echo '<li style="font-family: monospace; font-size: 12px; margin-bottom: 2px;">' . esc_html( $cap ) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
			echo '<p style="font-size: 11px; color: #646970; margin-top: 5px;">Total capabilities: ' . count( $active_caps ) . '</p>';
		} else {
			echo '<p>No capabilities found.</p>';
		}
	}

	/**
	 * Register the options used by this plugin
	 *
	 * @return void
	 */
	public function register_options(): void {
		\error_log( 'DMBC Plugin: register_options method called.' );
		\add_option( self::OPTION_VERSION, self::VERSION );
	}

	/**
	 * Add the capabilities necessary to manage tte DMBC custom types
	 *
	 * @return void
	 */
	public function add_songlist_capabilities() {
		\error_log( 'DMBC Plugin: add_songlist_capabilities method called.' );
		foreach ( $this->get_roles_with_edit_cap() as $role_name ) {
			$role = \get_role( $role_name );
			if ( $role && ! $role->has_cap( self::CAP_EDIT_SONGLIST ) ) {
				$role->add_cap( self::CAP_EDIT_SONGLIST );
				$role->add_cap( self::CAP_VIEW_SONGLISTS );
			}
		}
		foreach ( $this->get_roles_with_member_update_edit_cap() as $role_name ) {
			$role = \get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			if ( ! $role->has_cap( self::CAP_EDIT_MEMBER_UPDATES ) ) {
				$role->add_cap( self::CAP_EDIT_MEMBER_UPDATES );
			}
			if ( ! $role->has_cap( self::CAP_VIEW_MEMBER_UPDATES ) ) {
				$role->add_cap( self::CAP_VIEW_MEMBER_UPDATES );
			}
			if ( ! $role->has_cap( self::CAP_PUBLISH_MEMBER_UPDATES ) ) {
				$role->add_cap( self::CAP_PUBLISH_MEMBER_UPDATES );
			}
		}
		foreach ( $this->get_roles_with_view_cap() as $role_name ) {
			$role = \get_role( $role_name );
			if ( $role && ! $role->has_cap( self::CAP_VIEW_SONGLISTS ) ) {
				$role->add_cap( self::CAP_VIEW_SONGLISTS );
			}
			if ( $role && ! $role->has_cap( self::CAP_VIEW_MEMBER_UPDATES ) ) {
				$role->add_cap( self::CAP_VIEW_MEMBER_UPDATES );
			}
		}
	}

	/**
	 * Get the roles that can edit song lists.
	 *
	 * @return string[]
	 */
	private function get_roles_with_edit_cap(): array {
		return array( 'administrator', 'editor', 'um_director' );
	}

	/**
	 * Get the roles that can create and modify member updates.
	 *
	 * @return string[]
	 */
	private function get_roles_with_member_update_edit_cap(): array {
		return array( 'administrator', 'editor', 'um_director', 'um_member' );
	}

	/**
	 * Get the roles that can view song lists.
	 *
	 * @return string[]
	 */
	private function get_roles_with_view_cap(): array {
		return array( 'um_member' );
	}

	// Plugin deactivation and uninstall --------------------------------------------------

	/**
	 * Deactivate the plugin. Remove all the added hooks and filters.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		\error_log( 'DMBC Plugin: deactivate method called.' );
		\flush_rewrite_rules();
	}

	/**
	 * Uninstall the plugin. Removed all the database information and the database schema.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		\error_log( 'DMBC Plugin: uninstall method called.' );
		\delete_option( self::OPTION_VERSION );
	}
	/**
	 * Show a successful admin result message
	 *
	 * @param string $message The message to display.
	 * @return void
	 */
	public function admin_success( string $message ): void {
		\add_action(
			'admin_notices',
			fn() => "<div class=\"notice notice-info is-dismissible\"><p>{$message}</p></div>"
		);
	}

	// add_meta_boxes and save_post_dmbc-songlist ----------------------------------------

	/**
	 * Register the custom post type for song lists.
	 *
	 * @return void
	 */
	public function register_songlist_type(): void {
		\error_log( 'DMBC Plugin: register_songlist_type method called.' );

		if ( ! \post_type_exists( self::SONGLIST_POST_TYPE ) ) {
			\register_post_type(
				self::SONGLIST_POST_TYPE,
				array(
					'labels'       => array(
						'name'          => __( 'Song Lists', 'dmbc-tools' ),
						'singular_name' => __( 'Song List', 'dmbc-tools' ),
						'add_new_item'  => __( 'Add new Song List', 'dmbc-tools' ),
						'edit_item'     => __( 'Edit Song List', 'dmbc-tools' ),
					),
					'public '      => false,
					'show_in_rest' => true,
					'has_archive'  => true,
					'rewrite'      => array( 'slug' => 'songlists' ),
					'supports'     => array( 'title' ),
					'menu_icon'    => 'dashicons-playlist-audio',
				),
			);
		}
	}

	/**
	 * Register the custom post type for member updates.
	 *
	 * @return void
	 */
	public function register_member_update_type(): void {
		if ( \post_type_exists( self::MEMBER_UPDATE_POST_TYPE ) ) {
			return;
		}

		\register_post_type(
			self::MEMBER_UPDATE_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Member Updates', 'dmbc-tools' ),
					'singular_name' => __( 'Member Update', 'dmbc-tools' ),
					'add_new_item'  => __( 'Add Member Update', 'dmbc-tools' ),
					'edit_item'     => __( 'Edit Member Update', 'dmbc-tools' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'map_meta_cap' => false,
				'capabilities' => array(
					'edit_post'     => self::CAP_EDIT_MEMBER_UPDATES,
					'read_post'     => self::CAP_VIEW_MEMBER_UPDATES,
					'delete_post'   => self::CAP_EDIT_MEMBER_UPDATES,
					'edit_posts'    => self::CAP_EDIT_MEMBER_UPDATES,
					'publish_posts' => self::CAP_PUBLISH_MEMBER_UPDATES,
					'create_posts'  => self::CAP_EDIT_MEMBER_UPDATES,
				),
				'menu_icon'    => 'dashicons-megaphone',
			)
		);
	}

	/**
	 * Adds the meta box for song list details in the admin interface.
	 *
	 * @return void
	 */
	public function add_songlist_meta_box(): void {
		\error_log( 'DMBC Plugin: add_songlist_meta_box method called.' );
		\add_meta_box(
			'dmbc-songlist-details',
			__( 'Song list Details', 'dmbc-tools' ),
			array( $this, 'render_songlist_meta_box' ),
			self::SONGLIST_POST_TYPE,
			'normal',
			'high',
		);
	}

	/**
	 * Renders the meta box for song list details in the admin interface.
	 *
	 * @param \WP_Post $post The post object.
	 * @return void
	 */
	public function render_songlist_meta_box( \WP_Post $post ): void {
		\error_log( 'DMBC Plugin: render_songlist_meta_box method called.' );
		\wp_nonce_field( 'dmbc_save_songlist_meta', self::SONGLIST_META_NONCE );

		$performance_date = \get_post_meta( $post->ID, self::PERFORMANCE_DATE_META_KEY, true );
		$songs            = \get_post_meta( $post->ID, self::SONGS_META_KEY, false );
		$notes            = \get_post_meta( $post->ID, self::NOTES_META_KEY, true );
		?>
		<p>
			<label
				for="dmbc-performance-date"><strong><?php esc_html_e( 'Performance Date', 'dmbc-tools' ); ?></strong></label><br>
			<input id="dmbc-performance-date" name="dmbc_performance_date" type="date"
				value="<?php echo esc_attr( $performance_date ); ?>">
		</p>
		<p>
			<label for="dmbc-songs"><strong><?php esc_html_e( 'Songs', 'dmbc-tools' ); ?></strong></label><br>
			<textarea id="dmbc-songs" name="dmbc_songs" rows="10"
				class="widefat"><?php echo esc_textarea( $songs ); ?></textarea>
		</p>
		<p>
			<label for="dmbc-notes"><strong><?php esc_html_e( 'Notes', 'dmbc-tools' ); ?></strong></label><br>
			<textarea id="dmbc-notes" name="dmbc_notes" rows="5"
				class="widefat"><?php echo esc_textarea( $notes ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Saves the song list meta fields for a post.
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function save_songlist_meta( int $post_id ): void {
		\error_log( 'DMBC Plugin: save_songlist_meta method called.' );
		if (
		! isset( $_POST[ self::SONGLIST_META_NONCE ] ) ||
		! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST[ self::SONGLIST_META_NONCE ] ) ), 'dmbc_save_songlist_meta' ) ||
		( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
		! \current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$fields = array(
			'dmbc_performance_date' => self::PERFORMANCE_DATE_META_KEY,
			'dmbc_songs'            => self::SONGS_META_KEY,
			'dmbc_notes'            => self::NOTES_META_KEY,
		);

		foreach ( $fields as $field_name => $meta_key ) {
			if ( ! isset( $_POST[ $field_name ] ) ) {
				continue;
			}

			$value = 'dmbc_performance_date' === $field_name
			? \sanitize_text_field( \wp_unslash( $_POST[ $field_name ] ) )
			: \sanitize_textarea_field( \wp_unslash( $_POST[ $field_name ] ) );

			\update_post_meta( $post_id, $meta_key, $value );
		}
	}

	// admin_menu -------------------------------------------------------------------------

	/**
	 * Register the admin components for the plugin.
	 *
	 * @return void
	 */
	public function register_admin(): void {
		\error_log( 'DMBC Plugin: register_admin method called.' );
		$this->create_song_list_view();
		$this->create_member_update_view();
		$this->register_options();
		$this->add_admin_menu();
		$this->settings->register_settings();
	}

	/**
	 * Create the song list view table.
	 *
	 * @return void
	 */
	public function create_song_list_view(): void {
		\error_log( 'DMBC Plugin: create_song_list_view method called.' );

		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once __DIR__ . '/songlist-table.php';
		require_once __DIR__ . '/songlist-view.php';

		if ( ! isset( $this->song_list_view ) ) {
			$this->song_list_view = new SongListView( $this->settings );
		}
	}

	/**
	 * Create the member update view table.
	 *
	 * @return void
	 */
	public function create_member_update_view(): void {
		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once __DIR__ . '/member-update-table.php';
		require_once __DIR__ . '/member-update-view.php';

		if ( ! isset( $this->member_update_view ) ) {
			$this->member_update_view = new MemberUpdateView();
		}
	}

	/**
	 * Registers the plugin's admin menu pages .
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		\error_log( 'DMBC Plugin: add_admin_menu method called.' );
		$this->create_song_list_view();
		$this->create_member_update_view();

		add_menu_page(
			__( 'All Rehearsal Song Lists', 'dmbc-tools' ),
			__( 'Rehearsal Songs', 'dmbc-tools' ),
			self::CAP_VIEW_SONGLISTS,
			'dmbc-songlists-menu',
			array( $this->song_list_view, 'dmbc_render_songlist_table_page' ),
			'dashicons-playlist-audio',
			25
		);

		add_submenu_page(
			'dmbc-songlists-menu',
			__( 'Add Rehearsal Song list', 'dmbc-tools' ),
			__( 'Add Song list', 'dmbc-tools' ),
			self::CAP_EDIT_SONGLIST,
			'dmbc-songlist-edit',
			array( $this->song_list_view, 'dmbc_render_songlist_edit_page' )
		);

		add_menu_page(
			__( 'All Member Updates', 'dmbc-tools' ),
			__( 'Member Updates', 'dmbc-tools' ),
			self::CAP_VIEW_MEMBER_UPDATES,
			'dmbc-member-updates-menu',
			array( $this->member_update_view, 'render_member_update_table_page' ),
			'dashicons-megaphone',
			26
		);

		// add options page separately.
		add_submenu_page(
			'options-general.php',
			__( 'DMBC Tools', 'dmbc-tools' ),
			__( 'DMBC Tools', 'dmbc-tools' ),
			'manage_options',
			'dmbc-tools-settings',
			array( $this->settings, 'dmbc_render_settings_page' )
		);
	}
}
