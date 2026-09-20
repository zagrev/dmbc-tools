<?php
/**
 * Song list edit page template.
 *
 * @package DmbcTools
 *
 * @var int    $edit_id
 * @var string $edit_title
 * @var array  $edit_items
 * @var string $edit_notes
 * @var string $rehearsal_date
 * @var array  $song_folders
 * @var string $song_library_directory_path
 */

if ( ! \defined( 'ABSPATH' ) ) {
	return;
}

$verb                     = $edit_id > 0 ? __( 'Update Rehearsal Song List', 'dmbc-extras' ) : __( 'Add Rehearsal Song List', 'dmbc-extras' );
$delete_button_attributes = $edit_id > 0 ? 'style="background-color:#d63638 !important; border-color:#d63638 !important; color:#fff !important; padding:0.75rem 1.25rem; font-size:1rem;"' : 'disabled style="padding:0.75rem 1.25rem; font-size:1rem;"';
?>
<div class="wrap">
	<h1><?php echo esc_html( $verb ); ?></h1>

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
								<?php
								/* translators: %s: song library directory path. */
								echo esc_html( sprintf( __( 'Create folders inside %s to populate this selector.', 'dmbc-extras' ), $song_library_directory_path ) );
								?>
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
												<?php echo esc_html( DmbcTools\SongList::TYPE_NOTE === $item['type'] ? __( 'Note:', 'dmbc-extras' ) . ' ' . $item['value'] : $item['value'] ); ?>
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
