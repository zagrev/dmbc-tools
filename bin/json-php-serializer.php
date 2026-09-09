#!/usr/bin/env php
<?php
/**
 * Standalone CLI converter between JSON and PHP's serialize() format.
 *
 * Usage:
 *   php bin/json-php-serializer.php encode '{"a":1}'               JSON  -> PHP serialized string
 *   php bin/json-php-serializer.php decode 'a:1:{s:1:"a";i:1;}'    PHP serialized string -> JSON
 *   echo '{"a":1}' | php bin/json-php-serializer.php encode        Read input from STDIN
 *   php bin/json-php-serializer.php decode --file data.ser --pretty
 *
 * Direction aliases: encode|json2php and decode|php2json.
 *
 * Options:
 *   -f, --file <path>   Read input from a file instead of an argument/STDIN.
 *   -o, --output <path> Write output to a file instead of STDOUT.
 *   --objects           Encode: turn JSON objects into stdClass (O:8:"stdClass")
 *                       instead of associative arrays (a:...).
 *   --pretty            Decode: pretty-print the JSON output.
 *   --allow-classes     Decode: allow unserialize() to instantiate declared classes.
 *                       By default objects are converted with allowed_classes=false
 *                       and unknown classes appear as {"__class":"Name", ...}.
 *   -h, --help          Show this help.
 *
 * Exit codes: 0 success, 1 usage error, 2 input error, 3 conversion error.
 *
 * @package DmbcTools
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This tool must be run from the command line.\n" );
	exit( 1 );
}

define( 'J2P_EXIT_OK', 0 );
define( 'J2P_EXIT_USAGE', 1 );
define( 'J2P_EXIT_INPUT', 2 );
define( 'J2P_EXIT_CONVERSION', 3 );

/**
 * Print a message to STDERR and exit.
 *
 * @param string $message Error message.
 * @param int    $code    Exit code.
 * @return never
 */
function j2p_fail( $message, $code = J2P_EXIT_CONVERSION ) {
	fwrite( STDERR, 'json-php-serializer: ' . $message . "\n" );
	exit( $code );
}

/**
 * Print usage information.
 *
 * @return void
 */
function j2p_help() {
	$script = 'php bin/json-php-serializer.php';
	echo <<<"HELP"
Convert between JSON and PHP's serialize() format.

Usage:
  {$script} encode <json> [--objects] [-o file]
  {$script} decode <serialized> [--pretty] [--allow-classes] [-o file]
  {$script} encode|decode [-f file]        (or pipe input via STDIN)

Directions: encode, json2php  |  decode, php2json

Options:
  -f, --file <path>   Read input from a file
  -o, --output <path> Write output to a file
  --objects           Encode: JSON objects become stdClass instead of arrays
  --pretty            Decode: pretty-print JSON output
  --allow-classes     Decode: allow instantiating declared classes (default: safe)
  -h, --help          Show this help

HELP;
}

/**
 * Fetch the value that must follow an option like "-f" or "--file".
 *
 * @param array  $argv Raw argv.
 * @param int    $argc Raw argc.
 * @param int    $i    Index of the expected value.
 * @param string $opt  Option name for error reporting.
 * @return string
 */
function j2p_option_value( $argv, $argc, $i, $opt ) {
	if ( $i >= $argc ) {
		j2p_fail( "Option {$opt} requires a value.", J2P_EXIT_USAGE );
	}
	return (string) $argv[ $i ];
}

/**
 * Read the raw input string from a file, a positional argument, or STDIN.
 *
 * @param array $options     Parsed options.
 * @param array $positionals Parsed positional arguments.
 * @return string
 */
function j2p_read_input( $options, $positionals ) {
	$has_file = null !== $options['file'];
	$has_arg  = isset( $positionals[1] );

	if ( $has_file && $has_arg ) {
		j2p_fail( 'Provide input either as an argument or via --file/STDIN, not both.', J2P_EXIT_USAGE );
	}

	if ( $has_file ) {
		if ( ! is_readable( $options['file'] ) ) {
			j2p_fail( 'Cannot read input file: ' . $options['file'], J2P_EXIT_INPUT );
		}
		$data = file_get_contents( $options['file'] );
		if ( false === $data ) {
			j2p_fail( 'Failed reading input file: ' . $options['file'], J2P_EXIT_INPUT );
		}
		return $data;
	}

	if ( $has_arg ) {
		return $positionals[1];
	}

	if ( function_exists( 'stream_isatty' ) && stream_isatty( STDIN ) ) {
		j2p_fail( 'No input given. Pass a string, use --file, or pipe via STDIN.', J2P_EXIT_USAGE );
	}

	$data = stream_get_contents( STDIN );
	if ( false === $data || '' === $data ) {
		j2p_fail( 'No input received on STDIN.', J2P_EXIT_INPUT );
	}
	return $data;
}

/**
 * Write output to STDOUT or a file.
 *
 * @param string      $data   Output payload.
 * @param string|null $output Target file path or null for STDOUT.
 * @return void
 */
function j2p_write_output( $data, $output ) {
	if ( null === $output ) {
		echo $data, "\n";
		return;
	}
	if ( false === file_put_contents( $output, $data . "\n" ) ) {
		j2p_fail( 'Failed writing output file: ' . $output, J2P_EXIT_INPUT );
	}
}

/**
 * Convert JSON to a PHP serialized string.
 *
 * @param string $input   Raw JSON.
 * @param bool   $objects Decode JSON objects as stdClass instead of arrays.
 * @return string
 */
function j2p_json_to_serialized( $input, $objects ) {
	try {
		$value = json_decode( $input, ! $objects, 512, JSON_THROW_ON_ERROR );
	} catch ( \JsonException $e ) {
		j2p_fail( 'Invalid JSON: ' . $e->getMessage(), J2P_EXIT_CONVERSION );
	}
	return serialize( $value );
}

/**
 * Convert a PHP serialized string to JSON.
 *
 * @param string $input         Raw serialized data.
 * @param bool   $allow_classes Allow instantiating declared classes.
 * @param bool   $pretty        Pretty-print the JSON.
 * @return string
 */
function j2p_serialized_to_json( $input, $allow_classes, $pretty ) {
	$input = rtrim( $input, "\r\n\t " );
	if ( '' === $input ) {
		j2p_fail( 'Empty input: nothing to unserialize.', J2P_EXIT_INPUT );
	}

	$error = null;
	set_error_handler(
		static function ( $errno, $errstr ) use ( &$error ) {
			$error = $errstr;
			return true;
		},
		E_WARNING | E_NOTICE | E_DEPRECATED
	);
	try {
		$value = unserialize( $input, array( 'allowed_classes' => $allow_classes ) );
	} finally {
		restore_error_handler();
	}

	// unserialize() also returns false for the legit value serialize(false) === 'b:0;'.
	if ( false === $value && 'b:0;' !== $input ) {
		j2p_fail( 'Invalid serialized data: ' . ( $error ? $error : 'unserialize() failed' ), J2P_EXIT_CONVERSION );
	}

	$normalized = j2p_normalize( $value );
	j2p_warn_incomplete_classes( $value );

	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
	if ( $pretty ) {
		$flags |= JSON_PRETTY_PRINT;
	}

	try {
		return json_encode( $normalized, $flags | JSON_THROW_ON_ERROR );
	} catch ( \JsonException $e ) {
		j2p_fail( 'Cannot encode value as JSON: ' . $e->getMessage(), J2P_EXIT_CONVERSION );
	}
}

/**
 * Recursively convert __PHP_Incomplete_Class instances into plain stdClass
 * objects shaped like {"__class": "Original\\Name", ...properties}.
 *
 * @param mixed $value Value produced by unserialize().
 * @return mixed
 */
function j2p_normalize( $value ) {
	if ( $value instanceof \__PHP_Incomplete_Class ) {
		$raw             = (array) $value;
		$object          = new stdClass();
		$object->__class = isset( $raw['__PHP_Incomplete_Class_Name'] ) ? $raw['__PHP_Incomplete_Class_Name'] : 'unknown';
		unset( $raw['__PHP_Incomplete_Class_Name'] );

		foreach ( $raw as $key => $property ) {
			$clean = (string) $key;
			$null  = strrpos( $clean, "\0" );
			if ( false !== $null ) {
				// Strip the "\0*\0" / "\0Class\0" visibility prefixes.
				$clean = substr( $clean, $null + 1 );
			}
			$object->{$clean} = j2p_normalize( $property );
		}
		return $object;
	}

	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = j2p_normalize( $item );
		}
		return $value;
	}

	if ( is_object( $value ) ) {
		foreach ( get_object_vars( $value ) as $key => $item ) {
			$value->{$key} = j2p_normalize( $item );
		}
		return $value;
	}

	return $value;
}

/**
 * Collect __PHP_Incomplete_Class class names and print an informational
 * warning to STDERR so the JSON output on STDOUT stays clean.
 *
 * @param mixed $value Value produced by unserialize().
 * @return void
 */
function j2p_warn_incomplete_classes( $value ) {
	$names = array();
	$walk  = static function ( $item ) use ( &$walk, &$names ) {
		if ( $item instanceof \__PHP_Incomplete_Class ) {
			$raw     = (array) $item;
			$names[] = isset( $raw['__PHP_Incomplete_Class_Name'] ) ? $raw['__PHP_Incomplete_Class_Name'] : 'unknown';
			$item    = $raw;
		}
		if ( is_array( $item ) ) {
			foreach ( $item as $v ) {
				$walk( $v );
			}
		} elseif ( is_object( $item ) ) {
			foreach ( get_object_vars( $item ) as $v ) {
				$walk( $v );
			}
		}
	};
	$walk( $value );

	$names = array_unique( $names );
	if ( $names ) {
		fwrite(
			STDERR,
			'json-php-serializer: note: object(s) of undeclared class(es) '
			. implode( ', ', $names )
			. ' converted to {"__class": ...}. Use --allow-classes to instantiate them instead.' . "\n"
		);
	}
}

/*
 * -------------------------------------------------------------------------
 * Argument parsing and main flow.
 * -------------------------------------------------------------------------
 */

$options     = array(
	'file'          => null,
	'output'        => null,
	'objects'       => false,
	'pretty'        => false,
	'allow_classes' => false,
	'help'          => false,
);
$positionals = array();

for ( $i = 1; $i < $argc; $i++ ) {
	$arg = (string) $argv[ $i ];

	if ( '-h' === $arg || '--help' === $arg ) {
		$options['help'] = true;
	} elseif ( '--objects' === $arg ) {
		$options['objects'] = true;
	} elseif ( '--pretty' === $arg ) {
		$options['pretty'] = true;
	} elseif ( '--allow-classes' === $arg ) {
		$options['allow_classes'] = true;
	} elseif ( '-f' === $arg || '--file' === $arg ) {
		$options['file'] = j2p_option_value( $argv, $argc, ++$i, $arg );
	} elseif ( 0 === strpos( $arg, '--file=' ) ) {
		$options['file'] = substr( $arg, 7 );
	} elseif ( '-o' === $arg || '--output' === $arg ) {
		$options['output'] = j2p_option_value( $argv, $argc, ++$i, $arg );
	} elseif ( 0 === strpos( $arg, '--output=' ) ) {
		$options['output'] = substr( $arg, 9 );
	} elseif ( '' !== $arg && '-' === $arg[0] ) {
		j2p_fail( "Unknown option: {$arg}", J2P_EXIT_USAGE );
	} else {
		$positionals[] = $arg;
	}
}

if ( $options['help'] ) {
	j2p_help();
	exit( J2P_EXIT_OK );
}

$direction = isset( $positionals[0] ) ? strtolower( $positionals[0] ) : '';
$is_encode = in_array( $direction, array( 'encode', 'json2php' ), true );
$is_decode = in_array( $direction, array( 'decode', 'php2json' ), true );

if ( ! $is_encode && ! $is_decode ) {
	if ( '' === $direction ) {
		j2p_help();
	}
	j2p_fail( 'First argument must be a direction: encode|json2php or decode|php2json.', J2P_EXIT_USAGE );
}

$input = j2p_read_input( $options, $positionals );

if ( $is_encode ) {
	$result = j2p_json_to_serialized( $input, $options['objects'] );
} else {
	$result = j2p_serialized_to_json( $input, $options['allow_classes'], $options['pretty'] );
}

j2p_write_output( $result, $options['output'] );
exit( J2P_EXIT_OK );
