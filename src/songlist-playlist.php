<?php
/**
 * Song-list playlist helpers.
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
 * Builds downloadable song-file groups and playlist metadata for song lists.
 */
final class SongListPlaylist {
	/**
	 * Whether the current logged-in user has the member role.
	 *
	 * @return bool
	 */
	public static function current_user_is_member(): bool {
		$current_user = \wp_get_current_user();
		$user_roles   = is_array( $current_user->roles ?? null ) ? $current_user->roles : array();

		return \is_user_logged_in() && ( in_array( 'um_member', $user_roles, true ) || in_array( 'member', $user_roles, true ) );
	}

	/**
	 * Get download groups for the ordered song items.
	 *
	 * @param array<int, array{type: string, value: string}> $items Song-list items.
	 * @return array<int, array{song: string, folder_url: string, files: array<int, array{name: string, url: string}>}>
	 */
	public static function get_download_groups( array $items ): array {
		$song_library_dir = rtrim( self::get_song_library_directory(), '/' );
		$download_groups  = array();

		foreach ( $items as $item ) {
			if ( SongList::TYPE_SONG !== $item['type'] ) {
				continue;
			}

			$song_path   = self::normalize_path( (string) $item['value'] );
			$folder_path = self::is_absolute_path( $song_path ) ? $song_path : $song_library_dir . '/' . ltrim( $song_path, '/' );
			$folder_path = rtrim( self::normalize_path( $folder_path ), '/' );

			if ( ! str_starts_with( $folder_path . '/', $song_library_dir . '/' ) || ! is_dir( $folder_path ) ) {
				continue;
			}

			$files    = array();
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $folder_path, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() ) {
					continue;
				}

				$file_path = self::normalize_path( $file->getPathname() );
				$files[]   = array(
					'name' => basename( $file_path ),
					'url'  => self::make_content_relative_url( self::relative_to_content( $file_path ) ),
				);
			}

			if ( empty( $files ) ) {
				continue;
			}

			usort(
				$files,
				static fn( array $first, array $second ): int => strnatcasecmp( $first['name'], $second['name'] )
			);

			$download_groups[] = array(
				'song'       => $song_path,
				'folder_url' => self::get_song_folder_url( $song_path ),
				'files'      => $files,
			);
		}

		return $download_groups;
	}

	/**
	 * Get an ordered playlist for a song list, refreshing stored metadata when needed.
	 *
	 * @param int                                            $songlist_id Song-list post ID.
	 * @param array<int, array{type: string, value: string}> $items Song-list items.
	 * @return string[]
	 */
	public static function get_or_update_playlist( int $songlist_id, array $items ): array {
		$download_groups = self::get_download_groups( $items );
		return self::get_or_update_playlist_from_download_groups( $songlist_id, $download_groups );
	}

	/**
	 * Get an ordered playlist from download groups, refreshing stored metadata when needed.
	 *
	 * @param int                                                      $songlist_id Song-list post ID.
	 * @param array<int, array{files: array<int, array{url: string}>}> $download_groups Download groups.
	 * @return string[]
	 */
	public static function get_or_update_playlist_from_download_groups( int $songlist_id, array $download_groups ): array {
		$generated_playlist = self::get_playlist_urls_from_download_groups( $download_groups );
		$stored_playlist    = \get_post_meta( $songlist_id, Plugin::PLAYLIST_META_KEY, true );

		if ( ! is_array( $stored_playlist ) ) {
			$stored_playlist = array();
		}

		if ( $generated_playlist !== $stored_playlist ) {
			\update_post_meta( $songlist_id, Plugin::PLAYLIST_META_KEY, $generated_playlist );
			return $generated_playlist;
		}

		return $stored_playlist;
	}

	/**
	 * Flatten download groups into playlist URLs in song-list order.
	 *
	 * @param array<int, array{files: array<int, array{url: string}>}> $download_groups Download groups.
	 * @return string[]
	 */
	public static function get_playlist_urls_from_download_groups( array $download_groups ): array {
		$playlist_urls = array();

		foreach ( $download_groups as $download_group ) {
			foreach ( $download_group['files'] as $download_file ) {
				$playlist_urls[] = $download_file['url'];
			}
		}

		return $playlist_urls;
	}

	/**
	 * Get a browser URL to the song folder.
	 *
	 * @param string $song_path Stored song path.
	 * @return string
	 */
	public static function get_song_folder_url( string $song_path ): string {
		$song_library_dir  = rtrim( self::get_song_library_directory(), '/' );
		$song_path         = self::normalize_path( $song_path );
		$song_library_path = self::relative_to_content( $song_library_dir );
		$song_url_path     = self::is_absolute_path( $song_path )
			? self::relative_to_content( $song_path )
			: trim( $song_library_path . '/' . ltrim( $song_path, '/' ), '/' );

		return self::make_content_relative_url( rtrim( $song_url_path, '/' ) . '/' );
	}

	/**
	 * Resolve the configured song-library directory on disk.
	 *
	 * @return string
	 */
	private static function get_song_library_directory(): string {
		$directory = trim( str_replace( '\\', '/', (string) \get_option( Plugin::OPTION_SONGLIST_DIRECTORY, 'dmbc-song-library' ) ), '/' );
		if ( '' === $directory ) {
			$directory = 'dmbc-song-library';
		}

		return self::is_absolute_path( $directory )
			? self::normalize_path( $directory )
			: self::normalize_path( WP_CONTENT_DIR . '/' . $directory );
	}

	/**
	 * Normalize a filesystem path.
	 *
	 * @param string $path Path to normalize.
	 * @return string
	 */
	private static function normalize_path( string $path ): string {
		if ( function_exists( 'wp_normalize_path' ) ) {
			return \wp_normalize_path( $path );
		}

		return str_replace( '\\', '/', $path );
	}

	/**
	 * Whether a path is absolute.
	 *
	 * @param string $path Path to test.
	 * @return bool
	 */
	private static function is_absolute_path( string $path ): bool {
		$path = str_replace( '\\', '/', $path );

		return 1 === preg_match( '#^(?:[A-Za-z]:)?/#', $path );
	}

	/**
	 * Convert a filesystem path to a path relative to wp-content.
	 *
	 * @param string $path Path to convert.
	 * @return string
	 */
	private static function relative_to_content( string $path ): string {
		$path = self::normalize_path( $path );

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$content_dir = rtrim( self::normalize_path( WP_CONTENT_DIR ), '/' ) . '/';
			if ( str_starts_with( $path, $content_dir ) ) {
				return ltrim( substr( $path, strlen( $content_dir ) ), '/' );
			}
		}

		return ltrim( $path, '/' );
	}

	/**
	 * Encode a URL path while preserving separators.
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private static function encode_url_path( string $path ): string {
		$parts = array_map( 'rawurlencode', explode( '/', trim( $path, '/' ) ) );

		return implode( '/', $parts );
	}

	/**
	 * Build a URL under wp-content, keeping it relative to the current host when possible.
	 *
	 * @param string $path Path under wp-content.
	 * @return string
	 */
	private static function make_content_relative_url( string $path ): string {
		$url = \content_url( self::encode_url_path( $path ) );

		return function_exists( 'wp_make_link_relative' ) ? \wp_make_link_relative( $url ) : $url;
	}
}
