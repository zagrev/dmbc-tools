<?php
declare(strict_types=1);
namespace DmbcTools;

if ( ! \defined( 'ABSPATH' ) ) {
	print 'ABSPATH is not defined. This file (' . __FILE__ . ') should not be accessed directly.' . PHP_EOL;
	exit;
}

/**
 * A song list for rehearsals, etc.
 */
class SongList {
	/**
	 * Rehearsal item type for a linked song folder.
	 *
	 * @var string
	 */
	public const string TYPE_SONG = 'song';

	/**
	 * Rehearsal item type for a plain-text note (no link).
	 *
	 * @var string
	 */
	public const string TYPE_NOTE = 'note';

	/**
	 * The name of the song list
	 *
	 * @var string
	 */
	private string $name;
	/**
	 * The rehearsal items (songs and notes) in order.
	 *
	 * @var array<int, array{type: string, value: string}>
	 */
	private array $items = array();
	/**
	 * The date of the song list
	 *
	 * @var \DateTime
	 */
	private \DateTimeImmutable $rehearsal_date;
	/**
	 * Any notes to be included with the song list
	 *
	 * @var string
	 */
	private string $note;

	/**
	 * Create a song list from the given parameters
	 *
	 * @param string             $name the name of the song list.
	 * @param array              $items the rehearsal items (songs/notes). Legacy string
	 *                                  entries are treated as songs.
	 * @param \DateTimeImmutable $rehearsal_date the date of the rehearsal.
	 * @param string             $note any notes to be included with the song list.
	 */
	public function __construct( string $name, array $items, \DateTimeImmutable $rehearsal_date, string $note ) {
		$this->name           = $name;
		$this->items          = self::normalize_items( $items );
		$this->rehearsal_date = $rehearsal_date;
		$this->note           = $note;
	}

	/**
	 * Normalize raw rehearsal item data into a list of typed items.
	 *
	 * Accepts the legacy storage formats (a plain array of song names or a
	 * newline-separated string of song names) as well as the current format
	 * (arrays with 'type' and 'value'). Every returned entry has a 'type' of
	 * self::TYPE_SONG or self::TYPE_NOTE and a non-empty string 'value'.
	 *
	 * @param mixed $items Raw stored items.
	 * @return array<int, array{type: string, value: string}>
	 */
	public static function normalize_items( mixed $items ): array {
		if ( is_string( $items ) ) {
			$items = preg_split( '/\r\n|\r|\n/', $items );
		}
		if ( ! is_array( $items ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $items as $item ) {
			if ( is_string( $item ) || is_numeric( $item ) ) {
				$value = trim( (string) $item );
				if ( '' !== $value ) {
					$normalized[] = array(
						'type'  => self::TYPE_SONG,
						'value' => $value,
					);
				}
				continue;
			}
			if ( is_array( $item ) && isset( $item['value'] ) ) {
				$value = trim( (string) $item['value'] );
				if ( '' !== $value ) {
					$normalized[] = array(
						'type'  => isset( $item['type'] ) && self::TYPE_NOTE === $item['type'] ? self::TYPE_NOTE : self::TYPE_SONG,
						'value' => $value,
					);
				}
			}
		}
		return $normalized;
	}

	/**
	 * Get the name of the song list.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get the rehearsal items (songs and notes) in order.
	 *
	 * @return array<int, array{type: string, value: string}>
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Get the date of the rehearsal.
	 *
	 * @return \DateTime
	 */
	public function get_rehearsal_date(): \DateTimeImmutable {
		return $this->rehearsal_date;
	}

	/**
	 * Get any notes included with the song list.
	 *
	 * @return string
	 */
	public function get_note(): string {
		return $this->note;
	}
	/**
	 * Set the name of the song list.
	 *
	 * @param string $name the name of the song list.
	 */
	public function set_name( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Set the rehearsal items (songs and notes). Legacy string entries are
	 * treated as songs.
	 *
	 * @param array $items the rehearsal items.
	 */
	public function set_items( array $items ): void {
		$this->items = self::normalize_items( $items );
	}

	/**
	 * Set the date of the rehearsal.
	 *
	 * @param \DateTimeImmutable $rehearsal_date the date of the rehearsal.
	 */
	public function set_rehearsal_date( \DateTimeImmutable $rehearsal_date ): void {
		$this->rehearsal_date = $rehearsal_date;
	}

	/**
	 * Set any notes included with the song list.
	 *
	 * @param string $note any notes to be included with the song list.
	 */
	public function set_note( string $note ): void {
		$this->note = $note;
	}
}
