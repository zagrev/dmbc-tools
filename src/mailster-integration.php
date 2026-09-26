<?php
/**
 * WP Mailster group synchronization.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * Keeps a WP Mailster group in sync with the WordPress users holding the member roles.
 */
final class MailsterIntegration {

	/**
	 * The cron hook that runs the member group synchronization.
	 */
	public const string CRON_HOOK = 'dmbc_sync_mailster_member_group';

	/**
	 * The WP Mailster group that member-role users are added to.
	 */
	public const string DEFAULT_MAILSTR_GROUP_NAME = 'members';

	/**
	 * The WordPress roles treated as chorus members.
	 *
	 * @var string[]
	 */
	public const DEFAULT_USER_MEMBER_ROLES = array( 'um_member' );

	/**
	 * Get the name of the WP Mailster group to synchronize.
	 *
	 * @return string
	 */
	public static function mailstr_group_name(): string {
		$name = \apply_filters(
			'dmbc_mailster_group_name',
			self::DEFAULT_MAILSTR_GROUP_NAME
		);

		return \is_string( $name ) ? trim( $name ) : self::DEFAULT_MAILSTR_GROUP_NAME;
	}

	/**
	 * Get the WordPress roles whose users belong in the WP Mailster group.
	 *
	 * @return string[]
	 */
	public static function user_roles(): array {
		$roles = \apply_filters( 'dmbc_mailster_user_roles', self::DEFAULT_USER_MEMBER_ROLES );
		$roles = \is_array( $roles ) ? $roles : array( $roles );
		$roles = array_filter( array_map( fn( $role ): string => \sanitize_key( (string) $role ), $roles ) );

		return array_values( array_unique( $roles ) );
	}

	/**
	 * Add every member-role user to the WP Mailster group, skipping those already in it.
	 *
	 * @return int The number of users added to the group.
	 */
	public static function sync_members_to_group(): int {
		global $wpdb;

		$logger = Plugin::instance()->logger();

		$group_id = self::find_group_id( self::mailstr_group_name() );
		if ( 0 === $group_id ) {
			$logger->error( 'WP Mailster group not found, skipping member sync: ' . self::mailstr_group_name() );
			return 0;
		}

		$roles = self::user_roles();
		if ( empty( $roles ) ) {
			$logger->error( 'No member roles configured, skipping WP Mailster member sync.' );
			return 0;
		}

		$member_ids = array_map(
			'intval',
			(array) \get_users(
				array(
					'role__in' => $roles,
					'fields'   => 'ID',
				)
			)
		);
		if ( empty( $member_ids ) ) {
			$logger->info( 'No users found with the member roles, nothing to sync to WP Mailster.' );
			return 0;
		}

		$existing_ids = self::get_group_core_user_ids( $group_id );
		$missing_ids  = array_values( array_diff( $member_ids, $existing_ids ) );
		if ( empty( $missing_ids ) ) {
			$logger->info( 'All member-role users are already in the WP Mailster group: ' . self::mailstr_group_name() );
			return 0;
		}

		$added = 0;
		foreach ( $missing_ids as $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- WP Mailster exposes no API for its custom group tables.
			$inserted = $wpdb->insert(
				$wpdb->prefix . 'mailster_group_users',
				array(
					'group_id'     => $group_id,
					'user_id'      => $user_id,
					'is_core_user' => 1,
				),
				array( '%d', '%d', '%d' )
			);

			if ( false === $inserted ) {
				$logger->error( 'Failed to add user ' . $user_id . ' to WP Mailster group ' . $group_id . ': ' . $wpdb->last_error );
				continue;
			}

			++$added;
		}

		$logger->info( 'Added ' . $added . ' user(s) to the WP Mailster group: ' . self::mailstr_group_name() );

		return $added;
	}

	/**
	 * Look up a WP Mailster group id by its name.
	 *
	 * @param string $group_name The group name to find.
	 * @return int The group id, or 0 when the group or Mailster tables are missing.
	 */
	private static function find_group_id( string $group_name ): int {
		global $wpdb;

		if ( '' === $group_name ) {
			return 0;
		}

		$groups_table = $wpdb->prefix . 'mailster_groups';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name cannot be bound; WP Mailster has no API and this cron sync must read live data.
		$group_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$groups_table} WHERE name = %s LIMIT 1", $group_name ) );

		return null === $group_id ? 0 : (int) $group_id;
	}

	/**
	 * Get the WordPress user ids already recorded in the given WP Mailster group.
	 *
	 * @param int $group_id The WP Mailster group id.
	 * @return int[]
	 */
	private static function get_group_core_user_ids( int $group_id ): array {
		global $wpdb;

		$group_users_table = $wpdb->prefix . 'mailster_group_users';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name cannot be bound; WP Mailster has no API and this cron sync must read live data.
		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$group_users_table} WHERE group_id = %d AND is_core_user = 1", $group_id ) );

		return array_map( 'intval', (array) $user_ids );
	}
}
