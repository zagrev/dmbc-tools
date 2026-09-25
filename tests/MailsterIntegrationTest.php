<?php

use DmbcTools\MailsterIntegration;

/**
 * Tests for the WP Mailster group synchronization.
 */
final class MailsterIntegrationTest extends DmbcUnitTestBase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['dmbc_test_state']['wpdb_var'] = null;
		$GLOBALS['dmbc_test_state']['wpdb_col'] = array();
	}

	/** Register the users returned by get_users() with the ID field. */
	private function set_member_users( int ...$ids ): void {
		$GLOBALS['dmbc_test_state']['users'] = array_map(
			fn( int $id ) => (object) array( 'ID' => $id ),
			$ids
		);
	}

	/**
	 * A missing Mailster group aborts the sync without touching the group members table.
	 *
	 * @covers \DmbcTools\MailsterIntegration::sync_members_to_group
	 */
	public function test_sync_returns_zero_when_group_is_missing(): void {
		$GLOBALS['dmbc_test_state']['wpdb_var'] = null;
		$this->set_member_users( 5 );

		$this->assertSame( 0, MailsterIntegration::sync_members_to_group() );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['wpdb_inserts'] );
	}

	/**
	 * No users with the member roles means nothing is inserted.
	 *
	 * @covers \DmbcTools\MailsterIntegration::sync_members_to_group
	 */
	public function test_sync_returns_zero_when_no_member_users_exist(): void {
		$GLOBALS['dmbc_test_state']['wpdb_var'] = 7;
		$GLOBALS['dmbc_test_state']['users']    = array();

		$this->assertSame( 0, MailsterIntegration::sync_members_to_group() );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['wpdb_inserts'] );
	}

	/**
	 * Only users missing from the group are added, as core users.
	 *
	 * @covers \DmbcTools\MailsterIntegration::sync_members_to_group
	 */
	public function test_sync_adds_only_users_not_already_in_the_group(): void {
		$GLOBALS['dmbc_test_state']['wpdb_var'] = 7;
		$GLOBALS['dmbc_test_state']['wpdb_col'] = array( '11', '13' );
		$this->set_member_users( 11, 12, 13, 14 );

		$added = MailsterIntegration::sync_members_to_group();

		$this->assertSame( 2, $added );
		$this->assertCount( 2, $GLOBALS['dmbc_test_state']['wpdb_inserts'] );

		$inserted_ids = array_map(
			fn( array $insert ): int => $insert['data']['user_id'],
			$GLOBALS['dmbc_test_state']['wpdb_inserts']
		);
		$this->assertSame( array( 12, 14 ), $inserted_ids );

		$first = $GLOBALS['dmbc_test_state']['wpdb_inserts'][0];
		$this->assertSame( 'wp_mailster_group_users', $first['table'] );
		$this->assertSame( 7, $first['data']['group_id'] );
		$this->assertSame( 1, $first['data']['is_core_user'] );
	}

	/**
	 * Users already in the group are never inserted again.
	 *
	 * @covers \DmbcTools\MailsterIntegration::sync_members_to_group
	 */
	public function test_sync_is_idempotent_when_all_members_are_present(): void {
		$GLOBALS['dmbc_test_state']['wpdb_var'] = 7;
		$GLOBALS['dmbc_test_state']['wpdb_col'] = array( 11, 12 );
		$this->set_member_users( 11, 12 );

		$this->assertSame( 0, MailsterIntegration::sync_members_to_group() );
		$this->assertSame( array(), $GLOBALS['dmbc_test_state']['wpdb_inserts'] );
	}

	/**
	 * The sync requests users by the configured member roles.
	 *
	 * @covers \DmbcTools\MailsterIntegration::sync_members_to_group
	 */
	public function test_sync_queries_users_by_member_roles(): void {
		$GLOBALS['dmbc_test_state']['wpdb_var'] = 7;
		$this->set_member_users( 11 );

		MailsterIntegration::sync_members_to_group();

		$args = $GLOBALS['dmbc_test_state']['last_get_users_args'];
		$this->assertSame( array( 'members' ), $args['role__in'] );
		$this->assertSame( 'ID', $args['fields'] );
	}

	/**
	 * The group name and roles fall back to the member defaults.
	 *
	 * @covers \DmbcTools\MailsterIntegration::group_name
	 * @covers \DmbcTools\MailsterIntegration::member_roles
	 */
	public function test_group_name_and_roles_default_to_members(): void {
		$this->assertSame( 'members', MailsterIntegration::group_name() );
		$this->assertSame( array( 'members' ), MailsterIntegration::member_roles() );
	}
}
