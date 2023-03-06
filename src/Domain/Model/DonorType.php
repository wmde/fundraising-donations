<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @method static DonorType PERSON()
 * @method static DonorType COMPANY()
 * @method static DonorType EMAIL()
 * @method static DonorType ANONYMOUS()
 */
class DonorType {

	private string $value;

	private const TYPES = [
		'PERSON' => 'person',
		'COMPANY' => 'company',
		'EMAIL' => 'email',
		'ANONYMOUS' => 'anonymous'
	];

	protected function __construct( string $type ) {
		if ( !isset( self::TYPES[$type] ) ) {
			throw new \UnexpectedValueException( 'Invalid type: ' . $type );
		}
		$this->value = $type;
	}

	public static function __callStatic( $name, $arguments ): DonorType {
		return new self( $name );
	}

	public function __toString(): string {
		return self::TYPES[$this->value];
	}

	public static function make( string $value ): DonorType {
		$valueMap = array_flip( self::TYPES );
		if ( !isset( $valueMap[$value] ) ) {
			throw new \UnexpectedValueException( 'Invalid value: ' . $value );
		}
		return new self( $valueMap[$value] );
	}

	public function is( DonorType $donorType ): bool {
		return $this->value === $donorType->value;
	}

}
