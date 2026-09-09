<?php
/**
 * SongListView class file.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/songlist.php';

use DmbcTools\SongListTable;

/**
 * Class to render the standard song list pages
 */
class SongListView {
	/**
	 * The settings for this plugin.
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
	 * The table layout.
	 *
	 * @var SongListTable
	 */
	private SongListTable $song_list_table;

	/**
	 * Constructor.
	 *
	 * @param DmbcSettings $settings The plugin settings.
	 * @param Mailer|null  $mailer   The mailer used to send emails.
	 */
	public function __construct( DmbcSettings $settings, ?Mailer $mailer = null ) {

		$this->settings = $settings;
		$this->mailer   = $mailer ?? new Mailer( $settings );
		\add_action( 'admin_menu', array( $this, 'create_song_list_table' ) );
	}
	/**
	 * Creates the song list table instance.
	 */
	public function create_song_list_table(): void {
		// \error_log( 'DMBC SongListView: create_song_list_table method called.' );
		if ( ! isset( $this->song_list_table ) ) {
			$this->song_list_table = new SongListTable();
		}
	}
	/**
	 *
	 * Render the list of song lists page.
	 *
	 * @return void
	 */
	public function dmbc_render_songlist_table_page(): void {
		// \error_log( 'DMBC SongListView: dmbc_render_songlist_table_page method called.' );
		echo $this->render_member_song_lists_table_page();
	}

	/**
	 * Renders the admin page for editing a single song list.
	 *
	 * @param int $song_list_id The ID of the song list to edit.
	 * @return void
	 */
	public function dmbc_render_songlist_edit_page( $song_list_id = 0 ): void {
		// \error_log( 'DMBC SongListView: dmbc_render_songlist_edit_page method called.' );
		if ( 0 === (int) $song_list_id && isset( $_GET['song_list_id'] ) ) {
			$song_list_id = \absint( \wp_unslash( $_GET['song_list_id'] ) );
		}
		echo $this->render_song_list_edit_page( $song_list_id );
	}

	/**
	 * Convert a full file path to a relative path based on the given prefix.
	 *
	 * @param string $path_to_remove The prefix of the path to remove.
	 * @param string $full_path The full file path to convert.
	 * @return string The relative path.
	 */
	public static function convert_full_path_to_relative( string $path_to_remove, string $full_path ): string {
		$normalized_path = \wp_normalize_path( $full_path );
		return \str_replace( \wp_normalize_path( $path_to_remove ) . '/', '', $normalized_path );
	}

	/**
	 * Renders the song list view page.
	 *
	 * @param int         $song_list_id The ID of the song list to view.
	 * @param string|null $date The date of the rehearsal.
	 * @return void
	 */
	public function dmbc_render_song_list_view_page( $song_list_id = 0, $date = null ): void {
		echo $this->render_song_list_view_page( $song_list_id, $date );
	}

	/**
	 * Renders the song list table page.
	 *
	 * @return void
	 */
	public function dmbc_render_song_list_table_page(): void {
		echo $this->render_song_list_table_page();
	}

	/**
	 * Renders the song list view page.
	 *
	 * @param int         $song_list_id The ID of the song list to view.
	 * @param string|null $date The date of the rehearsal.
	 * @return string The HTML content of the song list view page.
	 */
	public function render_song_list_view_page( $song_list_id = 0, $date = null ): string {
		if ( ! \is_user_logged_in() ) {
			return '<p>Please log in to view this song list.</p>';
		}

		// if no id and no date provided, find the rehearsal song list that has the lowest date and is greater than or equal to today.
		$song_list = $song_list_id ? \get_post( $song_list_id ) : null;
		if ( ! $song_list && ! $date ) {
			$upcoming_song_lists = \get_posts(
				array(
					'post_type'      => Plugin::SONGLIST_POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'meta_value',
					'order'          => 'ASC',
					'meta_key'       => Plugin::PERFORMANCE_DATE_META_KEY,
					'meta_value'     => \current_time( 'Y-m-d' ),
					'meta_compare'   => '>=',
				)
			);
			$song_list           = ! empty( $upcoming_song_lists ) ? $upcoming_song_lists[0] : null;
		}

		if ( ( ! $song_list || Plugin::SONGLIST_POST_TYPE !== $song_list->post_type ) && ! $date ) {
			return '<p>Rehearsal Song List not found.</p>';
		}

		$song_list_title = \get_the_title( $song_list );
		$rehearsal_date  = \get_post_meta( $song_list->ID, Plugin::PERFORMANCE_DATE_META_KEY, true );
		if ( empty( $rehearsal_date ) ) {
			$rehearsal_date = date( 'Y-m-d', strtotime( $date ) );
		}
		$items = SongList::normalize_items( \get_post_meta( $song_list->ID, Plugin::SONGS_META_KEY, true ) );

		ob_start();
		?>
	<div class="dmbc-song-list-view">
		<h1><?php echo esc_html( $song_list_title ); ?> for <?php echo esc_html( $rehearsal_date ); ?></h1>

		<?php if ( ! empty( $items ) ) : ?>
			<h2>
			<?php
			esc_html_e( 'Rehearsal items', 'dmbc-extras' );
			$song_library_dir  = $this->settings->get_song_library_directory_path();
			$song_library_path = $this->convert_full_path_to_relative( WP_CONTENT_DIR, $song_library_dir );
			?>
			</h2>
			<ul>
				<?php foreach ( $items as $item ) : ?>
					<?php if ( SongList::TYPE_NOTE === $item['type'] ) : ?>
					<li class="dmbc-rehearsal-note"><?php echo esc_html( $item['value'] ); ?></li>
					<?php else : ?>
						<?php
						$song_path     = $item['value'];
						$song_url_path = $this->convert_full_path_to_relative( WP_CONTENT_DIR, $song_path );
						$song_url      = \content_url( "$song_library_path/$song_url_path" );
						?>
					<li><a href="<?php echo \esc_url( $song_url ); ?>"><?php echo esc_html( $song_path ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<?php else : ?>
			<p><?php esc_html_e( 'No songs selected for this list.', 'dmbc-extras' ); ?></p>
		<?php endif; ?>
	</div>
					<?php
					return ob_get_clean();
	}

	/**
	 * Render the page to delete a song list.
	 *
	 * @return bool|string
	 */
	public function dmbc_render_song_list_delete_page(): string {
		$_GET['action'] = 'view';
		$edit_id        = isset( $_GET['song_list_id'] ) ? intval( $_GET['song_list_id'] ) : 0;
		$view_page      = $this->render_song_list_view_page( $edit_id );

		ob_start();
		?>
	<form method="post" action="" id="dmbc_delete_song_list_form">
		<?php \wp_nonce_field( 'dmbc_delete_song_list', 'dmbc_song_list_delete_nonce' ); ?>
		<input type="hidden" name="dmbc_song_list_id" value="<?php echo esc_attr( $edit_id ); ?>">
		<?php
		\submit_button(
			__( 'Delete song list?', 'dmbc-extras' ),
			'warn large danger btn-danger',
			'dmbc_delete_song_list',
			false,
			'style="background-color:#d63638 !important; border-color:#d63638 !important; color:#fff !important;
		padding:0.75rem1.25rem; font-size:1rem;"'
		);
		?>
	</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the page that displays the song list table.
	 *
	 * @return string The HTML content of the song list table page.
	 */
	public function render_song_list_table_page(): string {
		global $dmbc_song_lists_table;

		if ( ! \is_user_logged_in() ) {
			return '<p>Please log in to view the rehearsal song lists.</p>';
		}

		if ( isset( $_GET['song_list_id'] ) ) {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'dmbc_song_list_request' ) ) {
				return '<p>Invalid song list request.</p>';
			}

			$song_list_id = absint( wp_unslash( $_GET['song_list_id'] ) );
			$action       = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
			if ( 'edit' === $action ) {
				return $this->dmbc_render_song_lists_admin_page();
			} elseif ( 'delete' === $action ) {
				return $this->dmbc_render_song_list_delete_page();
			}
			return $this->render_song_list_edit_page( $song_list_id );
		} else {
			return $this->render_member_song_lists_table_page();
		}
		return 'unexpected action, not edit/delete but has song_list_id=' . esc_html( $_GET['song_list_id'] );
	}

	/**
	 * Render the page to edit a song list.
	 *
	 * @param int $edit_id The ID of the song list to edit.
	 * @return string The HTML content of the song list edit page.
	 */
	public function render_song_list_edit_page( $edit_id = 0 ): string {
		$edit_post      = $edit_id > 0 ? \get_post( $edit_id ) : null;
		$edit_title     = '';
		$edit_items     = array();
		$edit_notes     = '';
		$rehearsal_date = ''; // Default to the next monday.
		$next_monday    = strtotime( 'next monday' );
		if ( $next_monday ) {
			$rehearsal_date = date( 'Y-m-d', $next_monday );
		}

		if ( $edit_post ) {
			$edit_title     = \get_the_title( $edit_post );
			$edit_items     = SongList::normalize_items( \get_post_meta( $edit_post->ID, Plugin::SONGS_META_KEY, true ) );
			$edit_notes     = \get_post_meta( $edit_post->ID, Plugin::NOTES_META_KEY, true );
			$rehearsal_date = \get_post_meta( $edit_post->ID, Plugin::PERFORMANCE_DATE_META_KEY, true );
		}

		$song_folders = $this->get_song_folder_choices();

		ob_start();
		?>
	<div class="wrap">
		<?php $action = $edit_id > 0 ? 'Update' : 'Add'; ?>
		<h1><?php esc_html_e( "$action Rehearsal Song List", 'dmbc-extras' ); ?></h1>

		<form method="post" action="" id="dmbc_edit_song_list_form">
		<?php \wp_nonce_field( 'dmbc_create_song_list', 'dmbc_song_list_nonce' ); ?>
			<input type="hidden" name="dmbc_song_list_id" value="<?php echo esc_attr( $edit_id ); ?>">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label
								for="dmbc_song_list_title"><?php esc_html_e( 'Song List Title', 'dmbc-extras' ); ?></label>
						</th>
						<td>
							<input type="text" id="dmbc_song_list_title" name="dmbc_song_list_title" class="regular-text"
								value="<?php echo esc_attr( $edit_title ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="dmbc-performance-date">
						<?php \esc_html_e( 'Rehearsal date', 'dmbc-extras' ); ?>
							</label>
						</th>
						<td>
							<input type="date" id="dmbc-performance-date" name="dmbc_performance_date"
								value="<?php echo \esc_attr( $rehearsal_date ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="dmbc-songs"><?php esc_html_e( 'Rehearsal items', 'dmbc-extras' ); ?></label>
						</th>
						<td>
							<?php if ( empty( $song_folders ) ) : ?>
								<p class="description">
									<?php echo esc_html( sprintf( __( 'Create folders inside %s to populate this selector.', 'dmbc-extras' ), $this->settings->get_song_library_directory_path() ) ); ?>
								</p>
							<?php else : ?>
								<div style="display:flex; gap:12px; align-items:flex-start;">
									<div>
										<label
											for="dmbc_available_song_folders"><?php esc_html_e( 'Available songs', 'dmbc-extras' ); ?></label>
										<select id="dmbc_available_song_folders" multiple size="10" class="large-text"
											style="min-width: 240px;">
											<?php foreach ( $song_folders as $song_path => $song_label ) : ?>
												<option value="<?php echo esc_attr( $song_path ); ?>">
													<?php echo esc_html( $song_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description">
											<?php esc_html_e( 'Double-click a folder to add it, or use multi-select and click Add Selected.', 'dmbc-extras' ); ?>
										</p>
									</div>
									<div style="display:flex; flex-direction:column; gap:8px; padding-top:24px;">
										<button type="button" id="dmbc_add_selected_song_folders"
											class="button button-secondary"><?php \esc_html_e( 'Add Selected', 'dmbc-extras' ); ?></button>
										<button type="button" id="dmbc_remove_selected_song_folders"
											class="button button-secondary"><?php \esc_html_e( 'Remove Selected', 'dmbc-extras' ); ?></button>
										<button type="button" id="dmbc_clear_selected_song_folders"
											class="button button-secondary"><?php \esc_html_e( 'Clear All', 'dmbc-extras' ); ?></button>
										<button type="button" id="dmbc_move_up_selected_song_folders"
											class="button button-secondary"><?php \esc_html_e( 'Move Up', 'dmbc-extras' ); ?></button>
										<button type="button" id="dmbc_move_down_selected_song_folders"
											class="button button-secondary"><?php \esc_html_e( 'Move Down', 'dmbc-extras' ); ?></button>
									</div>
									<div>
										<label
											for="dmbc_selected_song_folders"><?php \esc_html_e( 'Selected rehearsal items', 'dmbc-extras' ); ?></label>
										<select id="dmbc_selected_song_folders" multiple size="10"
											class="large-text" style="min-width: 240px;">
											<?php foreach ( $edit_items as $item ) : ?>
												<option value="<?php echo esc_attr( $item['value'] ); ?>" data-type="<?php echo esc_attr( $item['type'] ); ?>">
													<?php echo esc_html( SongList::TYPE_NOTE === $item['type'] ? __( 'Note:', 'dmbc-extras' ) . ' ' . $item['value'] : $item['value'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p>
											<label for="dmbc_new_note_text"><?php \esc_html_e( 'New note', 'dmbc-extras' ); ?></label>
											<input type="text" id="dmbc_new_note_text" class="regular-text" style="min-width: 240px;">
											<button type="button" id="dmbc_add_note_item"
												class="button button-secondary"><?php \esc_html_e( 'Add Note', 'dmbc-extras' ); ?></button>
										</p>
										<p class="description">
											<?php \esc_html_e( 'Songs link to the song library; notes are shown as plain text. Items are stored in the order shown.', 'dmbc-extras' ); ?>
										</p>
									</div>
								</div>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="dmbc-notes"><?php \esc_html_e( 'Notes', 'dmbc-extras' ); ?></label>
						</th>
						<td>
							<textarea id="dmbc-notes" name="dmbc_notes" rows="8"
								class="large-text"><?php echo \esc_textarea( $edit_notes ); ?></textarea>
						</td>
					</tr>
				</tbody>
			</table>
		<?php \submit_button( $edit_id > 0 ? __( 'Update Song List', 'dmbc-extras' ) : __( 'Create Song List', 'dmbc-extras' ) ); ?>
		</form>
		<div style="display:flex; justify-content:flex-end; margin-top:16px;">
			<form method="post" action="" id="dmbc_delete_song_list_form">
		<?php \wp_nonce_field( 'dmbc_delete_song_list', 'dmbc_song_list_delete_nonce' ); ?>
				<input type="hidden" name="dmbc_song_list_id" value="<?php echo esc_attr( $edit_id ); ?>">
		<?php
		$delete_button_attributes = $edit_id > 0 ? 'style="background-color:#d63638 !important; border-color:#d63638 !important; color:#fff !important; padding:0.75rem 1.25rem; font-size:1rem;"' : 'disabled style="padding:0.75rem 1.25rem; font-size:1rem;"';
		\submit_button(
			__( 'Delete Song List', 'dmbc-extras' ),
			'warn large danger btn-danger',
			'dmbc_delete_song_list',
			false,
			$delete_button_attributes
		);
		?>
			</form>
		</div>

	</div>
	<script>
		var dmbcDeleteConfirmation = <?php echo wp_json_encode( __( 'Are you sure you want to delete this song list?', 'dmbc-extras' ) ); ?>;
		jQuery(document).ready(function ($) {
			$('#dmbc_delete_song_list_form').on('submit', function (event) {
				if (!window.confirm(dmbcDeleteConfirmation)) {
					event.preventDefault();
				}
			});

			var $available = $('#dmbc_available_song_folders');
			var $selected = $('#dmbc_selected_song_folders');

			var addSelectedToList = function () {
				$available.find('option:selected').each(function () {
					var $option = $(this);
					if ($selected.find('option[value="' + $option.val() + '"]').length) {
						return;
					}
					$selected.append($('<option></option>').val($option.val()).text($option.text()).attr('data-type', 'song'));
				});
			};

			$available.on('dblclick', 'option', function () {
				var $option = $(this);
				if ($selected.find('option[value="' + $option.val() + '"]').length) {
					return;
				}
				$selected.append($('<option></option>').val($option.val()).text($option.text()).attr('data-type', 'song'));
			});

			$('#dmbc_add_note_item').on('click', function () {
				var noteText = $.trim($('#dmbc_new_note_text').val());
				if ('' === noteText) {
					return;
				}
				$selected.append($('<option></option>').val(noteText).text('Note: ' + noteText).attr('data-type', 'note'));
				$('#dmbc_new_note_text').val('').focus();
			});

			$available.on('keydown', function (event) {
				if ('Enter' === event.key || 13 === event.which) {
					event.preventDefault();
					addSelectedToList();
				}
			});

			$selected.on('dblclick', 'option', function () {
				$(this).remove();
			});

			$('#dmbc_add_selected_song_folders').on('click', addSelectedToList);

			$('#dmbc_remove_selected_song_folders').on('click', function () {
				$selected.find('option:selected').remove();
			});

			$('#dmbc_clear_selected_song_folders').on('click', function () {
				$selected.find('option').remove();
			});

			$('#dmbc_move_up_selected_song_folders').on('click', function () {
				var selected = $selected.find('option:selected');
				selected.each(function () {
					var $option = $(this);
					var prev = $option.prev();
					if (prev.length) {
						$option.insertBefore(prev);
					}
				});
			});

			$('#dmbc_move_down_selected_song_folders').on('click', function () {
				var selected = $selected.find('option:selected');
				$(selected.get().reverse()).each(function () {
					var $option = $(this);
					var next = $option.next();
					if (next.length) {
						$option.insertAfter(next);
					}
				});
			});

			$('#dmbc_edit_song_list_form').on('submit', function () {
				var $form = $(this);
				$form.find('input.dmbc-rehearsal-item-field').remove();
				$selected.find('option').each(function (index) {
					var $option = $(this);
					var type = 'note' === $option.attr('data-type') ? 'note' : 'song';
					$form.append($('<input type="hidden" class="dmbc-rehearsal-item-field" />').attr('name', 'dmbc_rehearsal_items[' + index + '][type]').val(type));
					$form.append($('<input type="hidden" class="dmbc-rehearsal-item-field" />').attr('name', 'dmbc_rehearsal_items[' + index + '][value]').val($option.val()));
				});
			});
		});
	</script>
			<?php
			return ob_get_clean();
	}

	/**
	 * Render the song list table view.
	 *
	 * @return bool|string
	 */
	public function render_member_song_lists_table_page(): string|bool {
		// \error_log( 'DMBC SongListView: render_member_song_lists_table_page called.' );

		$this->create_song_list_table();
		$this->song_list_table->prepare_items();
		ob_start();
		?>
		<div class="wrap">
			<h1>
		<?php esc_html_e( 'Rehearsal Song Lists', 'dmbc-extras' ); ?>
			</h1>
			<form method="post">
			<?php $this->song_list_table->display(); ?>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the rehearsal song lists admin page.
	 *
	 * @return string
	 */
	public function dmbc_render_song_lists_admin_page(): string {
		$edit_id = isset( $_GET['song_list_id'] ) ? \absint( \wp_unslash( $_GET['song_list_id'] ) ) : 0;
		return $this->render_song_list_edit_page( $edit_id );
	}

	/**
	 * Get the list of songs in the song library.
	 *
	 * @return array
	 */
	public function get_song_folder_choices() {
		$song_library_dir = $this->settings->get_song_library_directory_path();

		if ( ! is_dir( $song_library_dir ) ) {
			return array();
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $song_library_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		$choices = array();

		$exclusion_regexes = $this->settings->get_song_library_exclusion_regexes();
		foreach ( $iterator as $path ) {

			$full_path = \wp_normalize_path( $path->getpathname() );
			if ( $path->isDir() && ! \str_contains( $full_path, 'Archived Music' ) ) {
				$exclude = false;

				foreach ( $exclusion_regexes as $regex ) {
					if ( preg_match( '/' . $regex . '/', $full_path ) ) {
						$exclude = true;
						break;
					}
				}

				if ( ! $exclude ) {

					$relative_path = $this->convert_full_path_to_relative( $song_library_dir, $full_path );

					if ( ! empty( $relative_path ) ) {
						$choices[ $full_path ] = $relative_path;
					}
				}
			}
		}

		ksort( $choices, SORT_NATURAL | SORT_FLAG_CASE );

		return $choices;
	}

	/**
	 * Process the user's delete song list request.
	 *
	 * @return void
	 */
	public function handle_delete_song_list_form() {
		if ( ! isset( $_POST['dmbc_song_list_delete_nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['dmbc_song_list_delete_nonce'] ) ), 'dmbc_delete_song_list' ) ) {
			return;
		}

		if ( ! \current_user_can( Plugin::CAP_EDIT_SONGLIST ) && ! \current_user_can( 'manage_options' ) ) {
			die( 'You do not have permission to delete this song list.' );
		}

		$song_list_id = isset( $_POST['dmbc_song_list_id'] ) ? \absint( \wp_unslash( $_POST['dmbc_song_list_id'] ) ) : 0;
		if ( $song_list_id > 0 ) {
			$deleted_post = \wp_delete_post( $song_list_id, true );
			if ( $deleted_post ) {
				\add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rehearsal song list deleted successfully.', 'dmbc-extras' ) . '</p></div>';
					}
				);
				\add_action(
					'admin_menu',
					function () {
						\wp_safe_redirect( \admin_url( 'admin.php?page=dmbc-song-lists' ) );
						exit;
					}
				);
			} else {
				\add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Unable to delete the rehearsal song list.', 'dmbc-extras' ) . '</p></div>';
					}
				);
			}
		} else {
			\add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'No rehearsal song list was selected for deletion.', 'dmbc-extras' ) . '</p></div>';
				}
			);
		}
	}

	/**
	 * Sends a rehearsal song list to users who belong to configured roles.
	 *
	 * @param int        $song_list_id The rehearsal song list post ID.
	 * @param array|null $roles        Optional role slugs to notify.
	 * @return array<string> The recipients who were emailed.
	 */
	public function send_song_list_to_roles( int $song_list_id, array|null $roles = null ): array {

		$song_list = \get_post( $song_list_id );
		if ( ! $song_list || Plugin::SONGLIST_POST_TYPE !== $song_list->post_type ) {
			\error_log( 'DMBC SongListView: Cannot send song list to roles. Invalid song list ID ' . $song_list_id );
			return array();
		}

		$rehearsal_date = \get_post_meta( $song_list_id, Plugin::PERFORMANCE_DATE_META_KEY, true );
		$subject        = "Rehearsal song list: {$rehearsal_date}";
		$message        = \apply_filters( 'the_content', $song_list->post_content );

		return $this->mailer->send_email( $subject, $message, $roles );
	}

	/**
	 * Sends a rehearsal song list to users who belong to one role.
	 *
	 * @param int    $song_list_id The rehearsal song list post ID.
	 * @param string $role         The role slug whose members should receive the list.
	 * @return array<string> The recipients who were emailed.
	 */
	public function send_song_list_to_role( int $song_list_id, string $role ): array {
		return $this->send_song_list_to_roles( $song_list_id, array( $role ) );
	}

	/**
	 * Handles the rehearsal song list form submission.
	 *
	 * Validates the nonce and user capabilities, sanitizes submitted values,
	 * saves the song list post, and stores the selected songs in post meta.
	 *
	 * @return void
	 */
	public function handle_song_list_form(): void {
		// \error_log( 'DMBC SongListView: handle_song_list_form.' );
		if ( isset( $_POST['dmbc_delete_song_list'] ) ) {
			$this->handle_delete_song_list_form();
			return;
		}

		if ( ! isset( $_POST['dmbc_song_list_nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['dmbc_song_list_nonce'] ) ), 'dmbc_create_song_list' ) ) {
			return;
		}

		if ( ! \current_user_can( Plugin::CAP_EDIT_SONGLIST ) && ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$title          = isset( $_POST['dmbc_song_list_title'] ) ? \sanitize_text_field( \wp_unslash( $_POST['dmbc_song_list_title'] ) ) : '';
		$content        = isset( $_POST['dmbc_notes'] ) ? \wp_kses_post( \wp_unslash( $_POST['dmbc_notes'] ) ) : '';
		$song_list_id   = isset( $_POST['dmbc_song_list_id'] ) ? \absint( \wp_unslash( $_POST['dmbc_song_list_id'] ) ) : 0;
		$rehearsal_date = isset( $_POST['dmbc_performance_date'] ) ? \sanitize_text_field( \wp_unslash( $_POST['dmbc_performance_date'] ) ) : '';
		if ( ! empty( $rehearsal_date ) ) {
			$date = \DateTime::createFromFormat( '!Y-m-d', $rehearsal_date );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $rehearsal_date ) {
				$rehearsal_date = '';
			}
		}

		if ( isset( $_POST['dmbc_rehearsal_items'] ) && is_array( $_POST['dmbc_rehearsal_items'] ) ) {
			$song_library_dir = $this->settings->get_song_library_directory_path();
			$selected_items   = array();
			foreach ( \wp_unslash( $_POST['dmbc_rehearsal_items'] ) as $raw_item ) {
				if ( ! is_array( $raw_item ) || ! isset( $raw_item['value'] ) ) {
					continue;
				}
				$type  = isset( $raw_item['type'] ) && SongList::TYPE_NOTE === \sanitize_key( (string) $raw_item['type'] )
					? SongList::TYPE_NOTE
					: SongList::TYPE_SONG;
				$value = trim( (string) $raw_item['value'] );
				if ( SongList::TYPE_SONG === $type ) {
					$value = $this->convert_full_path_to_relative( $song_library_dir, $value );
				}
				if ( '' === $value ) {
					continue;
				}
				$selected_items[] = array(
					'type'  => $type,
					'value' => $value,
				);
			}

			$selected_items = array_values( array_unique( $selected_items, SORT_REGULAR ) );
		} else {
			\add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please select songs for the rehearsal song list.', 'dmbc-extras' ) . '</p></div>';
				}
			);
			return;
		}

		if ( empty( $title ) ) {
			\add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please enter a title for the rehearsal song list.', 'dmbc-extras' ) . '</p></div>';
				}
			);
			return;
		}

		$post_data = array(
			'ID'           => $song_list_id,
			'post_type'    => Plugin::SONGLIST_POST_TYPE,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
		);

		$post_id = $song_list_id > 0 ? \wp_update_post( $post_data, true ) : \wp_insert_post( $post_data, true );

		if ( \is_wp_error( $post_id ) ) {
			\add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error is-dismissible"><p>' . \esc_html__( 'Unable to save the rehearsal song list.', 'dmbc-extras' ) . '</p></div>';
				}
			);
			return;
		}

		/* Ensure selected rehearsal items are stored explicitly in post meta on updates and creates. */
		\update_post_meta( $post_id, Plugin::SONGS_META_KEY, $selected_items );
		\update_post_meta( $post_id, Plugin::PERFORMANCE_DATE_META_KEY, $rehearsal_date );
		\update_post_meta( $post_id, Plugin::NOTES_META_KEY, $content );

		\clean_post_cache( $post_id );
		$this->send_song_list_to_roles( $post_id );

		$action = 'created';
		if ( $song_list_id > 0 ) {
			$action = 'updated';
		}
		\add_action(
			'admin_notices',
			function () use ( $action ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rehearsal song list ' . $action . ' successfully.', 'dmbc-extras' ) . '</p></div>';
			}
		);
	}
} // End of SongListView class
