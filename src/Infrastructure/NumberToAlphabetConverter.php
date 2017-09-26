<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Infrastructure;

/**
 * Convert a number to a custom alphabet by converting it to a different base equal to the alphabet length
 * and mapping the re-based number to the alphabet characters.
 *
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class NumberToAlphabetConverter {

	private $alphabet;
	private $alphabetMap;

	public function __construct( string $alphabet ) {
		$this->alphabet = $alphabet;
		$base = strlen( $alphabet );
		if ( $base < 2 ) {
			throw new \InvalidArgumentException( 'Alphabet must contain at least two characters' );
		} elseif ( $base > 36 ) {
			throw new \InvalidArgumentException( 'Alphabet must not be longer than 36 characters' );
		}
		if ( count( array_unique( str_split( $alphabet ) ) ) !== $base ) {
			throw new \InvalidArgumentException( 'Alphabet characters must be unique' );
		}
		for ( $i=0; $i < $base; $i++ ) {
			$this->alphabetMap .= base_convert( $i, 10, $base );
		}
	}

	public function convertToAlphabet( int $number ): string {
		return strtr(
			base_convert( $number, 10, strlen( $this->alphabet ) ),
			$this->alphabetMap,
			$this->alphabet
		);
	}

	public function convertFromAlphabet( string $str ): int {
		return (int) base_convert(
			strtr( $str, $this->alphabet, $this->alphabetMap ),
			strlen( $this->alphabet ),
			10
		);
	}

	public function stripChars( string $string ): string {
		return preg_replace( '/[^' . preg_quote( $this->alphabet, '/' ) .']/', '', $string );
	}


}