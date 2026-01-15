<?php

/**
 * Minimal PDF text extractor used for training uploads.
 *
 * This is a lightweight parser that handles common FlateDecode streams and
 * standard text operators (Tj/TJ). It is not a full PDF implementation.
 *
 * @since      1.0.1
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 */
class Chat_Bot_Pdf_Parser {

	public function extract_text( $file_path ) {
		$content = file_get_contents( $file_path );
		if ( $content === false ) {
			return '';
		}

		$streams = $this->extract_streams( $content );
		if ( empty( $streams ) ) {
			return '';
		}

		$parts = array();
		foreach ( $streams as $stream ) {
			$data = $this->decode_stream( $stream['data'], $stream['filters'] );
			if ( $data === '' ) {
				continue;
			}

			$text = $this->extract_text_from_stream( $data );
			if ( $text !== '' ) {
				$parts[] = $text;
			}
		}

		return trim( implode( "\n", $parts ) );
	}

	private function extract_streams( $content ) {
		$streams = array();
		$pattern = '/<<(?P<dict>.*?)>>\\s*stream\\s*(?P<data>.*?)\\s*endstream/s';

		if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			return $streams;
		}

		foreach ( $matches as $match ) {
			$filters = $this->parse_filters( $match['dict'] );
			$streams[] = array(
				'filters' => $filters,
				'data' => $match['data'],
			);
		}

		return $streams;
	}

	private function parse_filters( $dict ) {
		$filters = array();

		if ( preg_match( '/\\/Filter\\s*\\/([A-Za-z0-9]+)/', $dict, $match ) ) {
			$filters[] = $match[1];
			return $filters;
		}

		if ( preg_match( '/\\/Filter\\s*\\[(.*?)\\]/s', $dict, $match ) ) {
			if ( preg_match_all( '/\\/([A-Za-z0-9]+)/', $match[1], $filter_matches ) ) {
				$filters = $filter_matches[1];
			}
		}

		return $filters;
	}

	private function decode_stream( $data, $filters ) {
		$data = ltrim( $data, "\r\n" );
		$data = rtrim( $data );

		if ( empty( $filters ) ) {
			return $data;
		}

		foreach ( $filters as $filter ) {
			if ( $filter === 'FlateDecode' ) {
				$decoded = $this->flate_decode( $data );
				if ( $decoded === '' ) {
					return '';
				}
				$data = $decoded;
				continue;
			}

			// Unsupported filter.
			return '';
		}

		return $data;
	}

	private function flate_decode( $data ) {
		$decoded = @gzuncompress( $data );
		if ( $decoded !== false ) {
			return $decoded;
		}

		$decoded = @gzinflate( $data );
		if ( $decoded !== false ) {
			return $decoded;
		}

		return '';
	}

	private function extract_text_from_stream( $data ) {
		$parts = array();

		if ( preg_match_all( '/\\[(.*?)\\]\\s*TJ/s', $data, $arrays ) ) {
			foreach ( $arrays[1] as $array_text ) {
				preg_match_all( '/\\((?:\\\\.|[^\\\\()])*\\)/', $array_text, $strings );
				foreach ( $strings[0] as $string ) {
					$parts[] = $this->decode_pdf_string( $string );
				}
			}
		}

		if ( preg_match_all( '/(\\((?:\\\\.|[^\\\\()])*\\))\\s*(Tj|\\\'|\\")/s', $data, $matches ) ) {
			foreach ( $matches[1] as $string ) {
				$parts[] = $this->decode_pdf_string( $string );
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$text = implode( ' ', array_filter( $parts ) );
		$text = preg_replace( "/[ \\t]+/", ' ', $text );
		$text = preg_replace( "/\\s*\\n\\s*/", "\n", $text );
		return trim( $text );
	}

	private function decode_pdf_string( $string ) {
		if ( $string === '' ) {
			return '';
		}

		$text = substr( $string, 1, -1 );

		$text = preg_replace_callback(
			'/\\\\([0-7]{1,3})/',
			function ( $match ) {
				return chr( octdec( $match[1] ) );
			},
			$text
		);

		$replacements = array(
			'\\n' => "\n",
			'\\r' => "\r",
			'\\t' => "\t",
			'\\b' => "\b",
			'\\f' => "\f",
			'\\(' => '(',
			'\\)' => ')',
			'\\\\' => '\\',
		);

		return strtr( $text, $replacements );
	}
}
