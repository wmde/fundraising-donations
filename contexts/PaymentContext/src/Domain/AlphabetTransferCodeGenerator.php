<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\PaymentContext\Domain;

use Closure;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\NumberToAlphabetConverter;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AlphabetTransferCodeGenerator implements TransferCodeGenerator {

	private $numberConverter;
	private $checksumFunction;
	private $codeGeneratorFunction;

	const ALPHABET = 'ACDEFKLMNPRTWXYZ349'; // 19 chars
	const LOWER_BOUND = 5153632; // 10000 in base19
	const UPPER_BOUND = 47045880; // IIIIII in base19

	private function __construct( Closure $codeGeneratorFunction, Closure $checksumFunction, NumberToAlphabetConverter $converter ) {
		$this->codeGeneratorFunction = $codeGeneratorFunction;
		$this->numberConverter = $converter;
		$this->checksumFunction = $checksumFunction;
	}

	public static function randomCodeGenerator(): self {
		return new self(
			function () { return mt_rand( self::LOWER_BOUND, self::UPPER_BOUND ); },
			function ( string $number ) {
				$checksum = iso7064_mod11_2( $number );
				return $checksum === 'X' ? 10 : (int) $checksum;
			},
			new NumberToAlphabetConverter( self::ALPHABET )
		);
	}

	/**
	 * Only for testing
	 */
	public static function deterministicAlphanumericCodeGenerator(): self {
		$counter = ( function () {
			$count = 0;
			while (true) yield $count++;
		} )();
		return new self(
			function () use ( $counter ) { $counter->next(); return $counter->current(); },
			function ( string $number ) {return (int) $number; },
			new NumberToAlphabetConverter( '0123456789' )
		);
	}

	/**
	 * Only for testing
	 */
	public static function deterministicHexCodeGenerator(): self {
		$counter = ( function () {
			$count = 0;
			while (true) yield $count++;
		} )();
		return new self(
			function () use ( $counter ) { $counter->next(); return $counter->current(); },
			function ( string $number ) {return (int) $number; },
			new NumberToAlphabetConverter( '0123456789ABCDEF' )
		);
	}

	public function generateTransferCode( string $prefix ): string {
		$code = $this->codeGeneratorFunction->call( $this );
		$integerPrefix = $this->numberConverter->convertFromAlphabet( $prefix );
		$checksum = $this->checksumFunction->call( $this, $integerPrefix . $code );
		return sprintf( '%s%s-%s',
			$prefix,
			$this->formatCode( $this->numberConverter->convertToAlphabet( $code) ),
			$this->numberConverter->convertToAlphabet( $checksum )
		);
	}

	private function formatCode( string $code ): string {
		return implode( '-', str_split( $code, 3 ) );
	}

	public function validateCode( string $code ): bool {
		$strippedCode = $this->numberConverter->stripChars( $code );
		if ( strlen( $strippedCode ) !== 9 ) {
			return false;
		}
		return $this->validateChecksum( $strippedCode );
	}

	private function validateChecksum( string $code ): bool {
		$prefix = substr( $code, 0, 2 );
		$checksumChar = substr( $code, -1 );
		$onlyCode = substr( $code, 2, -1 );
		$checksum = $this->checksumFunction->call( $this,
			$this->numberConverter->convertFromAlphabet( $prefix ) .
			$this->numberConverter->convertFromAlphabet( $onlyCode )
		);
		return $this->numberConverter->convertFromAlphabet( $checksumChar ) === $checksum;
	}
}