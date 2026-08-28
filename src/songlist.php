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
	 * The name of the song list
	 *
	 * @var string
	 */
	private string $name;
	/**
	 * The list of songs
	 *
	 * @var array
	 */
	private array $songs = array();
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
	 * @param array              $songs the list of songs.
	 * @param \DateTimeImmutable $rehearsal_date the date of the rehearsal.
	 * @param string             $note any notes to be included with the song list.
	 */
	public function __construct( string $name, array $songs, \DateTimeImmutable $rehearsal_date, string $note ) {
		$this->name           = $name;
		$this->songs          = $songs;
		$this->rehearsal_date = $rehearsal_date;
		$this->note           = $note;
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
	 * Get the list of songs.
	 *
	 * @return array
	 */
	public function get_songs(): array {
		return $this->songs;
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
	 * Set the list of songs.
	 *
	 * @param array $songs the list of songs.
	 */
	public function set_songs( array $songs ): void {
		$this->songs = $songs;
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
