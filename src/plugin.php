<?php
/**
 * Plugin functionality for DMBC Tools.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined . This file( ' . __FILE__ . ' ) should not be accessed directly . ' . PHP_EOL;
	exit;
}


require_once __DIR__ . '/admin/settings-edit.php';
require_once __DIR__ . '/admin/menu.php';
require_once __DIR__ . '/songlist.php';
require_once __DIR__ . '/songlist-playlist.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/deluxe-cc-transaction.php';
require_once __DIR__ . '/acf-integration.php';
require_once __DIR__ . '/mailster-integration.php';
require_once __DIR__ . '/logger.php';

use DmbcTools\SongListView;
use DmbcTools\DmbcSettings;
use DmbcTools\AcfIntegration;

/**
 * The DMBC Plugin
 */
final class Plugin {

	public const string CAP_EDIT_MEMBER_UPDATES           = 'dmbc_edit_member_updates';
	public const string CAP_EDIT_SONGLIST                 = 'dmbc_edit_songlist';
	public const string CAP_PUBLISH_MEMBER_UPDATES        = 'dmbc_publish_member_updates';
	public const string CAP_VIEW_MEMBER_UPDATES           = 'dmbc_view_member_updates';
	public const string CAP_VIEW_SONGLISTS                = 'dmbc_view_songlist';
	public const string MEMBER_UPDATE_CRON_HOOK           = 'dmbc_send_member_update_digest';
	public const string MEMBER_UPDATE_POST_TYPE           = 'dmbc-member-updates';
	public const string MEMBER_UPDATE_RECIPIENT_META_KEY  = '_dmbc_member_update_recipient';
	public const string MEMBER_UPDATE_SENT_META_KEY       = '_dmbc_member_update_sent_at';
	public const string NOTES_META_KEY                    = '_dmbc_notes';
	public const string OPTION_EMAIL_RECIPIENT            = 'member_update_recipient';
	public const string OPTION_MAX_BCC_PER_EMAIL          = 'max_bcc_recipients_per_email';
	public const string OPTION_REMOVE_DATA_ON_UNINSTALL   = 'remove_data_on_uninstall';
	public const string OPTION_SONGLIST_DIRECTORY         = 'song_library_directory';
	public const string OPTION_SONGLIST_EXCLUSION_REGEXES = 'song_library_exclusion_regexes';
	public const string OPTION_SONGLIST_RECIPIENT_ROLES   = 'song_list_recipient_roles';
	public const string OPTION_VERSION                    = 'dmbc_tools_version';
	public const string PERFORMANCE_DATE_META_KEY         = '_dmbc_performance_date';
	public const string PLAYLIST_META_KEY                 = '_dmbc_playlist';
	public const string SONGLIST_META_NONCE               = 'dmbc_songlist_meta_nonce';
	public const string SONGLIST_POST_TYPE                = 'dmbc-songlist';
	public const string SONGS_META_KEY                    = '_dmbc_songs';
	public const string VERSION                           = '1.1.24';

	/**
	 *  The settings used by the plugin.
	 *
	 * @var DmbcSettings
	 */
	private DmbcSettings $settings;

	/**
	 * The mailer used to send plugin emails.
	 *
	 * @var Mailer
	 */
	private Mailer $mailer;

	/**
	 * The handler for Deluxe credit card transaction notifications.
	 *
	 * @var DeluxeCcTransaction
	 */
	private DeluxeCcTransaction $deluxe_cc_transaction;

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
	 * The ticket view handler.
	 *
	 * @var TicketView
	 */
	private TicketView $ticket_view;

	/**
	 * The singleton instance of this plugin
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * The application logger for this plugin.
	 *
	 * @var DmbcLogger
	 */
	private DmbcLogger $logger;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->settings              = new DmbcSettings();
		$this->mailer                = new Mailer( $this->settings );
		$this->deluxe_cc_transaction = new DeluxeCcTransaction();
		if ( \defined( 'PHPUNIT_COMPOSER_INSTALL' ) || \defined( '__PHPUNIT_PHAR__' ) ) {
			// Application is running in a PHPUnit test environment.
			$this->logger = new DmbcLogger( 'dmbc-tools', DmbcLogger::LEVEL_OFF );
		} else {
			$this->logger = new DmbcLogger( 'dmbc-tools', DmbcLogger::LEVEL_INFO );
		}
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
	 * Return the application logger.
	 *
	 * @return DmbcLogger
	 */
	public function logger(): DmbcLogger {
		return $this->logger;
	}

	/**
	 * Register the hooks that start the plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		\add_action( 'init', array( $this, 'initialize' ) );
		\add_action( 'admin_init', array( $this, 'handle_admin_init' ) );
		\add_action( 'admin_menu', array( $this, 'register_admin' ) );
		\add_action( 'admin_menu', array( $this, 'unregister_old_post_types' ), 99 );
		\add_shortcode( 'dmbc_songlist', array( $this, 'render_songlist_shortcode' ) );

		\add_action( 'add_meta_boxes', array( $this, 'add_songlist_meta_box' ) );
		\add_action( 'save_post_' . self::SONGLIST_POST_TYPE, array( $this, 'save_songlist_meta' ) );
		\add_action( self::MEMBER_UPDATE_CRON_HOOK, array( $this, 'send_member_update_digest' ) );
		\add_action( MailsterIntegration::CRON_HOOK, array( MailsterIntegration::class, 'sync_members_to_group' ) );
		\add_action( 'rest_api_init', array( $this, 'register_deluxe_cc_notification_route' ) );

		\add_action( 'wp_dashboard_setup', array( $this, 'register_user_capabilities_dashboard_widget' ) );
		\add_action( 'wp_ajax_dmbc_browse_directory', array( $this->settings, 'ajax_browse_directory' ) );

		\add_action( 'wp_ajax_run_member_updates', array( $this->settings, 'ajax_run_member_updates' ) );
		\add_action( 'wp_ajax_run_member_groups_sync', array( $this->settings, 'ajax_run_member_groups_sync' ) );
	}

	// init -------------------------------------------------------------------------------.

	/**
	 * Set up everyting for the Plugin. This method is called at 'init' time.
	 *
	 * @return void
	 */
	public function initialize(): void {

		$this->register_songlist_type();
		$this->register_member_update_type();
		$this->register_options();
		$this->add_songlist_capabilities();
		$this->schedule_member_update_digest();
		$this->schedule_mailster_member_sync();
		DeluxeCcTransaction::create_table();
		AcfIntegration::register_acf_fields();

		\add_action( 'wp_dashboard_setup', array( self::instance(), 'register_menu_slugs_dashboard_widget' ) );
		\add_action( 'pre_get_posts', array( $this, 'order_songlist_archive_query' ) );
		\add_filter( 'template_include', array( $this, 'dmbc_single_songlist_template' ) );
		\add_filter( 'acf/format_value/name=telephone', array( $this, 'format_acf_phone_number' ) );
		\add_filter( 'acf/format_value/key=field_6aa438ce4ed00', array( $this, 'format_acf_phone_number' ) );
	}

	/**
	 * Order the public song-list archive by rehearsal date, newest first.
	 *
	 * @param \WP_Query $query The query being prepared.
	 * @return void
	 */
	public function order_songlist_archive_query( $query ): void {
		if ( \is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( self::SONGLIST_POST_TYPE ) ) {
			return;
		}

		$query->set( 'meta_key', self::PERFORMANCE_DATE_META_KEY );
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'DESC' );
	}

	/**
	 * Handle submitted song-list forms before the admin menu is rendered.
	 *
	 * @return void
	 */
	public function handle_admin_init(): void {
		$this->add_songlist_capabilities();
		$this->create_song_list_view();
		$this->song_list_view->handle_song_list_form();
	}

	// REST API ----------------------------------------------------------------------------.

	/**
	 * Registers the REST route that receives Deluxe credit card transaction notifications.
	 *
	 * @return void
	 */
	public function register_deluxe_cc_notification_route(): void {
		\register_rest_route(
			'dmbc',
			'/deluxe_cc_notification',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->deluxe_cc_transaction, 'handle_deluxe_cc_notification' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Register the custom dashboard widget.
	 */
	public function register_user_capabilities_dashboard_widget() {

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

		\add_option( self::OPTION_VERSION, self::VERSION );
		\update_option( self::OPTION_VERSION, self::VERSION ); // in case it already exists.

		\add_option( self::OPTION_SONGLIST_DIRECTORY, 'dmbc-song-library' );
		\add_option( self::OPTION_EMAIL_RECIPIENT, 'dmbc@daytonmetrobarbershopchorus.org' );
		\add_option( self::OPTION_MAX_BCC_PER_EMAIL, '50' );
		\add_option( self::OPTION_REMOVE_DATA_ON_UNINSTALL, false );
		\add_option( self::OPTION_SONGLIST_RECIPIENT_ROLES, 'um_member' );
	}

	/**
	 * Add the capabilities necessary to manage tte DMBC custom types
	 *
	 * @return void
	 */
	public function add_songlist_capabilities() {

		foreach ( $this->get_role_capability_grants() as $role_name => $capabilities ) {
			$role = \get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				if ( ! $role->has_cap( $capability ) ) {
					$role->add_cap( $capability );
				}
			}
		}
	}

	/**
	 * Get the capabilities this plugin grants to each managed role.
	 *
	 * @return array<string, string[]>
	 */
	private function get_role_capability_grants(): array {
		$editor_capabilities = array(
			self::CAP_EDIT_SONGLIST,
			self::CAP_VIEW_SONGLISTS,
			self::CAP_EDIT_MEMBER_UPDATES,
			self::CAP_VIEW_MEMBER_UPDATES,
			self::CAP_PUBLISH_MEMBER_UPDATES,
		);

		return array(
			'administrator' => $editor_capabilities,
			'editor'        => $editor_capabilities,
			'um_director'   => $editor_capabilities,
			'um_member'     => array(
				self::CAP_EDIT_MEMBER_UPDATES,
				self::CAP_VIEW_MEMBER_UPDATES,
				self::CAP_PUBLISH_MEMBER_UPDATES,
				self::CAP_VIEW_SONGLISTS,
			),
		);
	}

	// Plugin deactivation and uninstall --------------------------------------------------.

	/**
	 * Deactivate the plugin. Remove all the added hooks and filters.
	 *
	 * @return void
	 */
	public function deactivate(): void {

		\wp_clear_scheduled_hook( self::MEMBER_UPDATE_CRON_HOOK );
		\wp_clear_scheduled_hook( MailsterIntegration::CRON_HOOK );

		\flush_rewrite_rules();
	}

	/**
	 * Schedule the recurring member update digest.
	 *
	 * @return void
	 */
	public function schedule_member_update_digest(): void {
		if ( ! \wp_next_scheduled( self::MEMBER_UPDATE_CRON_HOOK ) ) {
			\wp_schedule_event( \time(), 'daily', self::MEMBER_UPDATE_CRON_HOOK );
		}
	}

	/**
	 * Schedule the recurring synchronization of member-role users into the WP Mailster group.
	 *
	 * @return void
	 */
	public function schedule_mailster_member_sync(): void {
		if ( ! \wp_next_scheduled( MailsterIntegration::CRON_HOOK ) ) {
			\wp_schedule_event( \time(), 'daily', MailsterIntegration::CRON_HOOK );
		}
	}

	/**
	 * Email all member updates that have changed since their last delivery.
	 *
	 * @return void
	 */
	public function send_member_update_digest(): void {
		$updates = \get_posts(
			array(
				'post_type'      => self::MEMBER_UPDATE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$updates = array_values(
			array_filter(
				$updates,
				function ( \WP_Post $update ): bool {
					$sent_at = \get_post_meta( $update->ID, self::MEMBER_UPDATE_SENT_META_KEY, true );
					return empty( $sent_at ) || $update->post_modified_gmt > $sent_at;
				}
			)
		);
		if ( empty( $updates ) ) {
			return;
		}

		$subject   = __( 'Member Updates', 'dmbc - tools' );
		$post_list = '';
		foreach ( $updates as $post ) {
			$post_title   = \get_the_title( $post );
			$raw_content  = \apply_filters( 'the_content', $post->post_content );
			$featured_img = \get_the_post_thumbnail_url( $post->ID, 'large' );

			// 2. Inline standard styles to raw web HTML so email clients don't break them
			$email_content = str_replace( '<p>', '<p style="margin:0 0 16px 0; font-size:16px; line-height:1.6; color:#444444;">', $raw_content );
			$email_content = str_replace( '<h2>', '<h2 style="margin:24px 0 12px 0; font-size:20px; color:#222222; font-family:Arial, sans-serif;">', $email_content );
			$email_content = str_replace( '<a ', '<a style="color:#0073aa; text-decoration:underline;" ', $email_content );

			$post_list .= "<tr><td><h1 style='font-size:28px; margin:0 0 20px 0;'>{$post_title}</h1></td></tr>";
			$post_list .= ( $featured_img ? "<tr><td style='padding-bottom:25px;'><img src='{$featured_img}' width='100%' /></td></tr>" : '' );
			$post_list .= "<tr><td>{$email_content}</td></tr>";
		}

		// 3. Inject variables into your template string
		$message = "
<!DOCTYPE html>
<html>
<body style='margin:0; padding:0; background-color:#f6f6f6; font-family:Arial, sans-serif;'>
    <table width='100%' bgcolor='#f6f6f6' style='padding:20px 0;'>
        <tr>
            <td align='center'>
                <table width='600' bgcolor='#ffffff' style='padding:40px; border-radius:8px;'>
                    
                    <tr><td>{$post_list}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";

		$recipients = $this->mailer->send_email( $subject, $message, array( 'um_member' ) );
		if ( empty( $recipients ) ) {
			return;
		}

		$sent_at = \gmdate( 'Y-m-d H:i:s' );
		foreach ( $updates as $update ) {
			\update_post_meta( $update->ID, self::MEMBER_UPDATE_SENT_META_KEY, $sent_at );
			\update_post_meta( $update->ID, self::MEMBER_UPDATE_RECIPIENT_META_KEY, $recipients );
		}
	}

	/**
	 * Uninstall the plugin. Removed all the database information and the database schema.
	 *
	 * @return void
	 */
	public static function uninstall(): void {

		// If uninstall.php is not called by WordPress, die immediately.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			exit();
		}
		if ( ! (bool) \get_option( self::OPTION_REMOVE_DATA_ON_UNINSTALL, false ) ) {
			return;
		}

		foreach ( array( self::SONGLIST_POST_TYPE, self::MEMBER_UPDATE_POST_TYPE ) as $post_type ) {
			$cpt_posts = get_posts(
				array(
					'post_type'   => $post_type,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids', // Only fetch IDs to save memory.
				)
			);

			if ( ! empty( $cpt_posts ) ) {
				foreach ( $cpt_posts as $post_id ) {
					// True forces deletion and bypasses the Trash.
					wp_delete_post( $post_id, true );
				}
			}
		}
		\add_action(
			'init',
			array( self::class, 'unregister_old_post_types' ),
			11
		);

		foreach (
		array(
			self::OPTION_VERSION,
			'song_library_directory',
			'song_library_exclusion_regexes',
			'song_list_recipient_roles',
			self::OPTION_EMAIL_RECIPIENT,
			self::OPTION_REMOVE_DATA_ON_UNINSTALL,
		) as $option_name
		) {
			\delete_option( $option_name );
		}
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

	// add_meta_boxes and save_post_dmbc-songlist ----------------------------------------.

	/**
	 * Register the custom post type for song lists.
	 *
	 * @return void
	 */
	public function register_songlist_type(): void {

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
					'public'       => true,
					'show_ui'      => false,
					'show_in_rest' => true,
					'has_archive'  => true,
					'rewrite'      => array( 'slug' => 'songlists' ),
					'supports'     => array( 'title' ),
					'menu_icon'    => 'dashicons-playlist-audio',
				),
			);
		}

		add_filter( 'pre_get_block_templates', array( $this, 'register_songlist_templates' ), 10, 3 );
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
				'labels'          => array(
					'name'          => __( 'Member Updates', 'dmbc-tools' ),
					'singular_name' => __( 'Member Update', 'dmbc-tools' ),
					'add_new_item'  => __( 'Add Member Update', 'dmbc-tools' ),
					'edit_item'     => __( 'Edit Member Update', 'dmbc-tools' ),
				),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => true,
				'has_archive'     => true,
				'rewrite'         => array( 'slug' => 'member-updates' ),
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'map_meta_cap'    => false,
				'capabilities'    => array(
					'edit_post'              => self::CAP_EDIT_MEMBER_UPDATES,
					'read_post'              => self::CAP_VIEW_MEMBER_UPDATES,
					'delete_post'            => self::CAP_EDIT_MEMBER_UPDATES,
					'edit_posts'             => self::CAP_EDIT_MEMBER_UPDATES,
					'edit_others_posts'      => self::CAP_EDIT_MEMBER_UPDATES,
					'edit_private_posts'     => self::CAP_EDIT_MEMBER_UPDATES,
					'edit_published_posts'   => self::CAP_EDIT_MEMBER_UPDATES,
					'publish_posts'          => self::CAP_PUBLISH_MEMBER_UPDATES,
					'read_private_posts'     => self::CAP_VIEW_MEMBER_UPDATES,
					'delete_posts'           => self::CAP_EDIT_MEMBER_UPDATES,
					'delete_others_posts'    => self::CAP_EDIT_MEMBER_UPDATES,
					'delete_private_posts'   => self::CAP_EDIT_MEMBER_UPDATES,
					'delete_published_posts' => self::CAP_EDIT_MEMBER_UPDATES,
					'create_posts'           => self::CAP_EDIT_MEMBER_UPDATES,
				),
				'menu_icon'       => 'dashicons-megaphone',
			)
		);

		add_filter( 'pre_get_block_templates', array( $this, 'register_member_update_templates' ), 10, 3 );
	}

	/**
	 * Adds the meta box for song list details in the admin interface.
	 *
	 * @return void
	 */
	public function add_songlist_meta_box(): void {

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

		\wp_nonce_field( 'dmbc_save_songlist_meta', self::SONGLIST_META_NONCE );

		$performance_date = \get_post_meta( $post->ID, self::PERFORMANCE_DATE_META_KEY, true );
		$items            = SongList::normalize_items( \get_post_meta( $post->ID, self::SONGS_META_KEY, true ) );
		$item_lines       = implode(
			"\n",
			array_map(
				fn( $item ) => SongList::TYPE_NOTE === $item['type'] ? 'note: ' . $item['value'] : $item['value'],
				$items
			)
		);
		$notes            = \get_post_meta( $post->ID, self::NOTES_META_KEY, true );
		?>
		<p>
			<label
				for="dmbc-performance-date"><strong><?php esc_html_e( 'Performance Date', 'dmbc-tools' ); ?></strong></label><br>
			<input id="dmbc-performance-date" name="dmbc_performance_date" type="date"
				value="<?php echo esc_attr( $performance_date ); ?>">
		</p>
		<p>
			<label for="dmbc-songs"><strong><?php esc_html_e( 'Rehearsal items', 'dmbc-tools' ); ?></strong></label><br>
			<textarea id="dmbc-songs" name="dmbc_songs" rows="10"
				class="widefat"><?php echo esc_textarea( $item_lines ); ?></textarea>
			<span class="description"><?php esc_html_e( 'One item per line. Prefix a line with "note:" to show it as plain text instead of a song link.', 'dmbc-tools' ); ?></span>
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

			if ( 'dmbc_songs' === $field_name ) {
				$value = self::parse_rehearsal_item_lines( $value );
			}

			\update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Parse newline-separated rehearsal item text into typed items.
	 *
	 * Lines prefixed with "note:" become plain-text note items; every other
	 * non-empty line becomes a song item.
	 *
	 * @param string $text The raw textarea content.
	 * @return array<int, array{type: string, value: string}>
	 */
	private static function parse_rehearsal_item_lines( string $text ): array {
		$items = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			if ( 0 === stripos( $line, 'note:' ) ) {
				$note = trim( substr( $line, 5 ) );
				if ( '' !== $note ) {
					$items[] = array(
						'type'  => SongList::TYPE_NOTE,
						'value' => $note,
					);
				}
				continue;
			}
			$items[] = array(
				'type'  => SongList::TYPE_SONG,
				'value' => $line,
			);
		}
		return $items;
	}

	// admin_menu -------------------------------------------------------------------------.

	/**
	 * Register the admin components for the plugin.
	 *
	 * @return void
	 */
	public function register_admin(): void {

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

		if ( ! isset( $this->song_list_view ) ) {
			$this->ensure_wp_list_table_available();
			require_once __DIR__ . '/songlist-table.php';
			require_once __DIR__ . '/songlist-view.php';

			$this->song_list_view = new SongListView( $this->settings, $this->mailer );
		}
	}

	/**
	 * Load the WordPress list table base class before creating admin tables.
	 *
	 * @return void
	 */
	private function ensure_wp_list_table_available(): void {
		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
	}

	/**
	 * Create the member update view table.
	 *
	 * @return void
	 */
	public function create_member_update_view(): void {
		if ( ! isset( $this->member_update_view ) ) {
			$this->ensure_wp_list_table_available();
			require_once __DIR__ . '/member-update-table.php';
			require_once __DIR__ . '/member-update-view.php';

			$this->member_update_view = new MemberUpdateView();
		}
	}

	/**
	 * Create the ticket transaction view table.
	 *
	 * @return void
	 */
	public function create_ticket_view(): void {
		if ( ! isset( $this->ticket_view ) ) {
			$this->ensure_wp_list_table_available();
			require_once __DIR__ . '/ticket-table.php';
			require_once __DIR__ . '/ticket-view.php';

			$this->ticket_view = new TicketView();
		}
	}

	/**
	 * Unregister post types that we used to use but don't use anymore.
	 *
	 * @return void
	 */
	public static function unregister_old_post_types(): void {
		foreach ( array( 'rehearsal-notes' ) as $post_type ) {
			// Remove any left-over rehearsal notes menu page.
			\remove_menu_page( $post_type );
			\unregister_post_type( $post_type );
		}

		$legacy_caps = array( 'view-song-lists', 'edit_song_list' );
		$role_names  = array_keys( \wp_roles()->get_names() );
		$users       = \get_users();

		foreach ( $role_names as $role_name ) {
			$role = \get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $legacy_caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		foreach ( $users as $user ) {
			foreach ( $legacy_caps as $cap ) {
				$user->remove_cap( $cap );
			}
		}
	}

	/**
	 * Registers the plugin's admin menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {

		$this->create_song_list_view();
		$this->create_member_update_view();
		$this->create_ticket_view();

		\add_menu_page(
			__( 'All Rehearsal Song Lists', 'dmbc-tools' ),
			__( 'Rehearsal Songs', 'dmbc-tools' ),
			self::CAP_VIEW_SONGLISTS,
			'dmbc-songlists-menu',
			array( $this->song_list_view, 'dmbc_render_songlist_table_page' ),
			'dashicons-playlist-audio',
			25
		);

		\add_submenu_page(
			'dmbc-songlists-menu',
			__( 'Add Rehearsal Song list', 'dmbc-tools' ),
			__( 'Add Song list', 'dmbc-tools' ),
			self::CAP_EDIT_SONGLIST,
			'dmbc-songlist-edit',
			array( $this->song_list_view, 'dmbc_render_songlist_edit_page' )
		);

		\add_menu_page(
			__( 'All Member Updates', 'dmbc-tools' ),
			__( 'Member Updates', 'dmbc-tools' ),
			self::CAP_VIEW_MEMBER_UPDATES,
			'dmbc-member-updates-menu',
			array( $this->member_update_view, 'render_member_update_table_page' ),
			'dashicons-megaphone',
			26
		);

		\add_menu_page(
			__( 'Ticket Sales', 'dmbc-tools' ),
			__( 'Ticket Sales', 'dmbc-tools' ),
			'read', // TODO Set new capability.
			'dmbc-ticket-sales',
			array( $this->ticket_view, 'render_ticket_table_page' ),
			'dashicons-tickets-alt',
			27
		);

		// add options page separately.
		\add_submenu_page(
			'options-general.php',
			__( 'DMBC Tools', 'dmbc-tools' ),
			__( 'DMBC Tools', 'dmbc-tools' ),
			'manage_options',
			'dmbc-tools-settings',
			array( $this->settings, 'dmbc_render_settings_page' )
		);
	}

	/**
	 * Render the view of a single song list.
	 *
	 * @return void
	 */
	public function render_songlist_view_page() {
		$this->create_song_list_view();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$list_id = isset( $_GET['song_list_id'] ) ? (int) \sanitize_text_field( \wp_unslash( $_GET['song_list_id'] ) ) : 0;
		$this->song_list_view->dmbc_render_song_list_view_page( $list_id );
	}

	/**
	 * Register the WordPress Dashboard Widget
	 */
	public function register_menu_slugs_dashboard_widget() {
		\wp_add_dashboard_widget(
			'wp_admin_menu_slugs_widget',          // Widget slug.
			'Registered Admin Menu Slugs',         // Widget title.
			array( $this, 'render_menu_slugs_dashboard_widget' )   // Display callback function.
		);
	}

	/**
	 * Render the Widget Content
	 */
	public function render_menu_slugs_dashboard_widget() {
		global $menu, $submenu;

		// Optional styling to make the list scrollable and easy to read.
		echo '<style>
        .slugs-widget-container { max-height: 350px; overflow-y: auto; padding-right: 5px; }
        .slugs-parent { font-weight: bold; background: #f0f6fc; padding: 4px 8px; margin: 8px 0 4px 0; border-left: 4px solid #72aee6; font-family: monospace; }
        .slugs-sub-list { margin: 0 0 10px 15px; padding-left: 10px; border-left: 1px dashed #ccd0d4; font-family: monospace; list-style: none; }
        .slugs-sub-item { margin-bottom: 2px; }
        .slug-tag { background: #eaeaea; padding: 1px 4px; border-radius: 3px; font-size: 11px; color: #d63638; }
    </style>';

		echo '<div class="slugs-widget-container">';
		echo '<p>Below is a dynamic map of your site\'s currently active admin menu slugs:</p>';

		if ( ! empty( $menu ) ) {
			foreach ( $menu as $menu_item ) {
				// $menu_item[0] is the clean title, $menu_item[2] is the menu slug
				if ( empty( $menu_item[2] ) ) {
					continue;
				}

				$parent_slug  = $menu_item[2];
				$parent_title = wp_strip_all_tags( $menu_item[0] );

				// If it's just a separator, skip it.
				if ( strpos( $menu_item[4], 'wp-menu-separator' ) !== false ) {
					continue;
				}

				echo '<div class="slugs-parent">' . esc_html( $parent_title ) . ' ➡️ <span class="slug-tag">' . esc_html( $parent_slug ) . '</span></div>';

				// Check if this parent has submenus.
				if ( isset( $submenu[ $parent_slug ] ) && ! empty( $submenu[ $parent_slug ] ) ) {
					echo '<ul class="slugs-sub-list">';
					foreach ( $submenu[ $parent_slug ] as $sub_item ) {
						// $sub_item[0] is the sub title, $sub_item[2] is the sub slug
						$sub_title = wp_strip_all_tags( $sub_item[0] );
						$sub_slug  = $sub_item[2];

						echo '<li class="slugs-sub-item">— ' . esc_html( $sub_title ) . ': <span class="slug-tag">' . esc_html( $sub_slug ) . '</span></li>';
					}
					echo '</ul>';
				}
			}
		} else {
			echo '<p>No menus found.</p>';
		}

		echo '</div>';
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
		$this->mailer->send_email_using_template( $template_name, $ticket_data );
	}

	/**
	 * Intercepts the theme engine and loads the plugin's custom layout for dmbc-songlist pages.
	 *
	 * @param string $template Path to the default theme template file.
	 * @return string Path to the chosen template file.
	 */
	public function dmbc_single_songlist_template( $template ) {
		if ( \is_post_type_archive( self::SONGLIST_POST_TYPE ) ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/archive-songlist.php';

			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		if ( \is_singular( self::SONGLIST_POST_TYPE ) ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-songlist.php';

			// Check if the file actually exists to avoid throwing errors.
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Register the custom templates for the songlist post type.
	 *
	 * @param array  $query_result The current array of block templates.
	 * @param array  $query The query.
	 * @param string $template_type The template type requested.
	 */
	public function register_songlist_templates( $query_result, $query, $template_type ) {
		// We only want to handle regular block templates (not template parts).
		if ( 'wp_template' !== $template_type ) {
			return $query_result;
		}

		// Check if WordPress is looking for our specific templates.
		$slugs = isset( $query['slug__in'] ) ? $query['slug__in'] : array();

		$templates = array(
			'single-songlist'       => array(
				'file'  => 'single-songlist.html',
				'title' => 'Single Songlist',
			),
			'single-dmbc-songlist'  => array(
				'file'  => 'single-songlist.html',
				'title' => 'Single Songlist',
			),
			'archive-songlist'      => array(
				'file'  => 'archive-songlist.html',
				'title' => 'Songlist Archive',
			),
			'archive-dmbc-songlist' => array(
				'file'  => 'archive-songlist.html',
				'title' => 'Songlist Archive',
			),
		);

		foreach ( $slugs as $slug ) {
			$slug = self::normalize_block_template_slug( (string) $slug );
			if ( isset( $templates[ $slug ] ) ) {
				$template_file = plugin_dir_path( __FILE__ ) . 'templates/' . $templates[ $slug ]['file'];

				if ( file_exists( $template_file ) ) {
					$template        = new \WP_Block_Template();
					$template->type  = 'wp_template';
					$template->theme = get_stylesheet();
					$template->slug  = $slug;
					$template->id    = get_stylesheet() . '//' . $slug;
					$template->title = $templates[ $slug ]['title'];
					ob_start();
					include $template_file;
					$template->content   = (string) ob_get_clean();
					$template->source    = 'plugin';
					$template->status    = 'publish';
					$template->is_custom = true;

					// Return it inside an array as WordPress expects.
					return array( $template );
				}
			}
		}

		return $query_result;
	}


	/**
	 * Register the custom templates for the member update post type.
	 *
	 * @param array  $query_result The current array of block templates.
	 * @param array  $query The query.
	 * @param string $template_type The template type requested.
	 */
	public function register_member_update_templates( $query_result, $query, $template_type ) {
		// We only want to handle regular block templates (not template parts).
		if ( 'wp_template' !== $template_type ) {
			return $query_result;
		}

		// Check if WordPress is looking for our specific templates.
		$slugs = isset( $query['slug__in'] ) ? $query['slug__in'] : array();

		$templates = array(
			'single-member-update'        => array(
				'file'  => 'single-member-update.html',
				'title' => 'Single Member Update',
			),
			'single-dmbc-member-updates'  => array(
				'file'  => 'single-member-update.html',
				'title' => 'Single Member Update',
			),
			'archive-member-update'       => array(
				'file'  => 'archive-member-update.html',
				'title' => 'Member Update Archive',
			),
			'archive-dmbc-member-updates' => array(
				'file'  => 'archive-member-update.html',
				'title' => 'Member Update Archive',
			),
		);

		foreach ( $slugs as $slug ) {
			$slug = self::normalize_block_template_slug( (string) $slug );
			if ( isset( $templates[ $slug ] ) ) {
				$template_file = plugin_dir_path( __FILE__ ) . 'templates/' . $templates[ $slug ]['file'];

				if ( file_exists( $template_file ) ) {
					$template        = new \WP_Block_Template();
					$template->type  = 'wp_template';
					$template->theme = get_stylesheet();
					$template->slug  = $slug;
					$template->id    = get_stylesheet() . '//' . $slug;
					$template->title = $templates[ $slug ]['title'];
					ob_start();
					include $template_file;
					$template->content   = (string) ob_get_clean();
					$template->source    = 'plugin';
					$template->status    = 'publish';
					$template->is_custom = true;

					// Return it inside an array as WordPress expects.
					return array( $template );
				}
			}
		}

		return $query_result;
	}

	/**
	 * Normalize template lookup slugs that may arrive as theme-qualified IDs.
	 *
	 * @param string $slug Template slug or template ID.
	 * @return string Unqualified template slug.
	 */
	private static function normalize_block_template_slug( string $slug ): string {
		$slug = trim( str_replace( '//', '/', $slug ), '/' );

		if ( str_contains( $slug, '/' ) ) {
			$parts = explode( '/', $slug );
			return (string) end( $parts );
		}

		return $slug;
	}

	/**
	 * Render the current song-list post for block templates.
	 *
	 * @return string Song-list HTML.
	 */
	public function render_songlist_shortcode(): string {
		$songlist_id = \get_the_ID();

		if ( ! $songlist_id || self::SONGLIST_POST_TYPE !== \get_post_type( $songlist_id ) ) {
			return '';
		}

		$rehearsal_date = (string) \get_post_meta( $songlist_id, self::PERFORMANCE_DATE_META_KEY, true );

		ob_start();
		?>
		<article id="post-<?php echo \esc_attr( (string) $songlist_id ); ?>" class="dmbc-songlist-article">
			<header class="dmbc-songlist-header">
				<h1 class="dmbc-songlist-title"><?php echo \esc_html( \get_the_title( $songlist_id ) ); ?></h1>
				<?php if ( ! empty( $rehearsal_date ) ) : ?>
					<p class="dmbc-songlist-date">
						<?php \esc_html_e( 'Rehearsal date:', 'dmbc-tools' ); ?>
						<time datetime="<?php echo \esc_attr( $rehearsal_date ); ?>"><?php echo \esc_html( $rehearsal_date ); ?></time>
					</p>
				<?php endif; ?>
			</header>

			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_songlist_post() escapes dynamic values and returns plugin-owned markup.
			echo self::render_songlist_post( (int) $songlist_id );
			?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the song-list details for a single post.
	 *
	 * @param int $songlist_id Song-list post ID.
	 * @return string Song-list HTML.
	 */
	public static function render_songlist_post( int $songlist_id ): string {
		$dmbc_is_member          = SongListPlaylist::current_user_is_member();
		$items                   = SongList::normalize_items( \get_post_meta( $songlist_id, self::SONGS_META_KEY, true ) );
		$notes                   = (string) \get_post_meta( $songlist_id, self::NOTES_META_KEY, true );
		$download_groups         = $dmbc_is_member ? SongListPlaylist::get_download_groups( $items ) : array();
		$download_groups_by_song = array();

		foreach ( $download_groups as $download_group ) {
			$download_groups_by_song[ $download_group['song'] ] = $download_group;
		}

		$playlist_file_urls = $dmbc_is_member ? SongListPlaylist::get_or_update_playlist_from_download_groups( $songlist_id, $download_groups ) : array();
		$playlist_filename  = \sanitize_file_name( \get_the_title( $songlist_id ) . '.m3u' );

		ob_start();
		?>
		<div class="dmbc-songlist-content">
			<?php if ( ! empty( $items ) ) : ?>
				<ul class="dmbc-songlist-items">
					<?php foreach ( $items as $item ) : ?>
						<?php if ( SongList::TYPE_NOTE === $item['type'] ) : ?>
							<li class="dmbc-songlist-note"><?php echo \esc_html( $item['value'] ); ?></li>
						<?php else : ?>
							<?php
							$song_path      = \wp_normalize_path( (string) $item['value'] );
							$song_url       = SongListPlaylist::get_song_folder_url( $song_path );
							$download_group = $download_groups_by_song[ $song_path ] ?? null;
							?>
							<li class="dmbc-songlist-song">
								<?php if ( ! empty( $download_group ) ) : ?>
									<details class="dmbc-songlist-song-details">
										<summary class="dmbc-songlist-song-summary"><?php echo \esc_html( $song_path ); ?></summary>
										<div class="dmbc-songlist-song-panel">
											<a class="dmbc-songlist-folder-link" href="<?php echo \esc_url( $song_url ); ?>"><?php \esc_html_e( 'Open song folder', 'dmbc-tools' ); ?></a>
											<ul class="dmbc-songlist-download-files">
												<?php foreach ( $download_group['files'] as $download_file ) : ?>
													<li>
														<a href="<?php echo \esc_url( $download_file['url'] ); ?>" download><?php echo \esc_html( $download_file['name'] ); ?></a>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									</details>
								<?php else : ?>
									<a href="<?php echo \esc_url( $song_url ); ?>"><?php echo \esc_html( $song_path ); ?></a>
								<?php endif; ?>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php \esc_html_e( 'No songs selected for this list.', 'dmbc-tools' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $notes ) ) : ?>
				<section class="dmbc-songlist-notes" aria-label="<?php \esc_attr_e( 'Song list notes', 'dmbc-tools' ); ?>">
					<?php echo \wp_kses_post( \wpautop( $notes ) ); ?>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $playlist_file_urls ) ) : ?>
				<p class="dmbc-songlist-playlist">
					<a class="dmbc-songlist-playlist-download" href="#" download="<?php echo \esc_attr( $playlist_filename ); ?>" data-playlist-urls="<?php echo \esc_attr( \wp_json_encode( $playlist_file_urls ) ); ?>">
						<?php \esc_html_e( 'Download playlist', 'dmbc-tools' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Format the ACF telephone field.
	 *
	 * @param mixed $value The value of the ACF field.
	 * @return string The formatted phone number.
	 */
	public function format_acf_phone_number( $value ): string {
		$this->logger->info( 'formatting phone number (' . $value . ')' );
		// If the field is empty, return early.
		if ( empty( $value ) ) {
			return $value;
		}

		// Remove all non-numeric characters (spaces, dashes, extensions).
		$numbers_only = preg_replace( '/[^0-9]/', '', $value );

		// If it's a standard 10-digit US number, format it: (XXX) XXX-XXXX.
		if ( strlen( $numbers_only ) === 10 ) {
			$value = preg_replace( '/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $numbers_only );
		} elseif ( strlen( $numbers_only ) === 11 ) {
			$value = preg_replace( '/(\d{1})(\d{3})(\d{3})(\d{4})/', '+$1 ($2) $3-$4', $numbers_only );
		}
		$this->logger->info( 'returning phone number (' . $value . ')' );
		return $value;
	}
}
