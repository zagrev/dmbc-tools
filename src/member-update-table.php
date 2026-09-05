<?php
/**
 * MemberUpdateTable class file.
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
 * Displays member updates in the WordPress admin.
 */
class MemberUpdateTable extends \WP_List_Table {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Member Update', 'dmbc-tools' ),
				'plural'   => __( 'Member Updates', 'dmbc-tools' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Retrieve the columns for the table.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'title' => __( 'Title', 'dmbc-tools' ),
			'date'  => __( 'Published', 'dmbc-tools' ),
			'sent'  => __( 'Sent', 'dmbc-tools' ),
		);
	}

	/**
	 * Render the update title as a link to the standard WordPress editor.
	 *
	 * @param WP_Post $item The current update.
	 * @return string
	 */
	public function column_title( WP_Post $item ): string {
		$edit_url = \add_query_arg(
			array(
				'post'   => $item->ID,
				'action' => 'edit',
			),
			\admin_url( 'post.php' )
		);
		return sprintf( '<a href="%1$s"><strong>%2$s</strong></a>', \esc_url( $edit_url ), \esc_html( $item->post_title ) );
	}

	/**
	 * Render the publish date.
	 *
	 * @param WP_Post $item The current update.
	 * @return string
	 */
	public function column_date( WP_Post $item ): string {
		return \esc_html( $item->post_date );
	}

	/**
	 * Render the member update delivery timestamp.
	 *
	 * @param WP_Post $item The current update.
	 * @return string
	 */
	public function column_sent( WP_Post $item ): string {
		return \esc_html( (string) \get_post_meta( $item->ID, Plugin::MEMBER_UPDATE_SENT_META_KEY, true ) );
	}

	/**
	 * Configure the items shown in the table.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = \get_posts(
			array(
				'post_type'      => Plugin::MEMBER_UPDATE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$this->set_pagination_args(
			array(
				'total_items' => count( $this->items ),
				'per_page'    => 20,
			)
		);
	}
}
