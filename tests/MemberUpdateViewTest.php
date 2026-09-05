<?php

require_once dirname( __DIR__ ) . '/src/member-update-table.php';
require_once dirname( __DIR__ ) . '/src/member-update-view.php';

use DmbcTools\MemberUpdateTable;
use DmbcTools\MemberUpdateView;
use DmbcTools\Plugin;

/** @covers \DmbcTools\MemberUpdateView */
final class MemberUpdateViewTest extends DmbcUnitTestBase {
	public function test_table_queries_published_member_updates(): void {
		$post             = new \WP_Post( 12 );
		$post->post_type  = Plugin::MEMBER_UPDATE_POST_TYPE;
		$post->post_title = 'September update';
		$post->post_date  = '2026-09-04 12:00:00';
		$GLOBALS['dmbc_test_state']['posts'] = array( $post );

		$table = new MemberUpdateTable();
		$table->prepare_items();

		$this->assertSame( array( $post ), $table->get_items() );
		$this->assertSame( Plugin::MEMBER_UPDATE_POST_TYPE, $GLOBALS['dmbc_test_state']['last_get_posts_args']['post_type'] );
		$this->assertStringContainsString( 'post=12', $table->column_title( $post ) );
	}

	public function test_table_page_links_to_the_standard_wordpress_editor(): void {
		$post             = new \WP_Post( 24 );
		$post->post_type  = Plugin::MEMBER_UPDATE_POST_TYPE;
		$post->post_title = 'October update';
		$post->post_date  = '2026-10-01 09:00:00';
		$GLOBALS['dmbc_test_state']['posts'][24] = $post;

		ob_start();
		( new MemberUpdateView() )->render_member_update_table_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'post-new.php?post_type=dmbc-member-updates', $html );
		$this->assertStringContainsString( 'Member Updates', $html );
		$this->assertStringContainsString( '<form method="post">', $html );
		$this->assertStringContainsString( 'October update', $html );
	}
}
