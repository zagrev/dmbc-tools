<?php
/**
 * SongListTable class file.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

use WP_Post;

/**
 * Class SongListTable
 *
 * @package DmbcTools
 */
class SongListTable extends \WP_List_Table {
	/**
	 * Constructor.
	 */
	public function __construct() {
		\error_log( 'DMBC SongListTable: SongListTable constructor.' );
		parent::__construct(
			array(
				'singular' => __( 'Song List', 'dmbc-extras' ),
				'plural'   => __( 'Song Lists', 'dmbc-extras' ),
				'ajax'     => false,
			)
		);
	}
	/**
	 * Retrieve the list of columns for the table.
	 *
	 * @return array
	 */
	public function get_columns() {
		\error_log( 'DMBC SongListTable: get_columns.' );
		return array(
			'cb'             => '<input type="checkbox" />',
			'rehearsal_date' => __( 'Rehearsal Date', 'dmbc-extras' ),
			'name'           => __( 'Name', 'dmbc-extras' ),
			'songs'          => __( 'Songs', 'dmbc-extras' ),
		);
	}

	/**
	 * Specific renderer for the title column.
	 *
	 * @param WP_Post $item The current item.
	 * @return string
	 */
	public function column_name( WP_Post $item ) {
		return $item->post_title;
	}

	/**
	 * Specfic renderer for the rehearsal date.
	 *
	 * @param WP_Post $item The song list to be rendered.
	 * @return string
	 */
	public function column_rehearsal_date( WP_Post $item ) {
		$actions = array();
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );

			if ( \wp_verify_nonce( $nonce, 'edit_song_list_' . $item->ID ) ) {
				$page = isset( $_REQUEST['page'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['page'] ) ) : '';

				$actions['edit']   = sprintf(
					'<a href="?page=%s&action=%s&song_list_id=%s">Edit</a>',
					\esc_attr( $page ),
					'edit',
					$item->ID
				);
				$actions['delete'] = sprintf(
					'<a href="?page=%s&action=%s&song_list_id=%s">Delete</a>',
					\esc_attr( $page ),
					'delete',
					$item->ID
				);
			}
		}
		// Return rehearsal date with row actions.
		$base_url       = \is_admin() ? \admin_url( 'admin.php?page=dmbc-songlist-edit' ) : \get_permalink();
		$view_url       = \add_query_arg( array( 'song_list_id' => $item->ID ), $base_url );
		$rehearsal_date = \get_post_meta( $item->ID, Plugin::PERFORMANCE_DATE_META_KEY, true );
		return sprintf( '<a href="%1$s">%2$s</a> %3$s', \esc_url( $view_url ), \esc_html( $rehearsal_date ), $this->row_actions( $actions ) );
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param mixed $item The post needs the checkbox column.
	 * @return string
	 */
	public function column_cb( mixed $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-items[]" value="%s" />',
			esc_attr( $item->ID )
		);
	}

	/**
	 * Fallback renderer for all other columns.
	 *
	 * @param WP_Post $item The current item.
	 * @return string
	 */
	public function column_songs( WP_Post $item ) {
		$songs = \get_post_meta( $item->ID, Plugin::SONGS_META_KEY, true );
		return is_array( $songs ) ? implode( ', ', $songs ) : $songs;
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'rehearsal_date' => array( 'rehearsal_date', true ),
			'name'           => array( 'name', false ),
		);
	}

	/**
	 * Prepare the list of items for display.
	 *
	 * This method sets up the column headers, fetches the song list posts,
	 * handles pagination, and assigns the items to be displayed in the table.
	 *
	 * @return void
	 */
	public function prepare_items() {
		// 1. Define column headers
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// 2. Fetch your raw data (usually via $wpdb or an API)
		$song_lists = \get_posts(
			array(
				'post_type'   => Plugin::SONGLIST_POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'meta_value',
				'order'       => 'DESC',
				'meta_key'    => Plugin::PERFORMANCE_DATE_META_KEY,
			)
		);

		// 3. Define total counts and pagination configuration
		$per_page    = 10;
		$total_items = count( $song_lists );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		// 4. Assign data to items array
		$this->items = $song_lists;
	}
}
