<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

class DataReaderWithDefault {

	/**
	 * @var array<string,mixed>
	 */
	private array $data;

	/**
	 * @param array<string,mixed> $data
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function getValue( string $key ): string {
		$value = $this->data[$key] ?? '';
		if ( !is_scalar( $value ) ) {
			throw new \DomainException( "Trying to access a non-string value in data blob: " . $key );
		}
		return strval( $value );
	}
}
