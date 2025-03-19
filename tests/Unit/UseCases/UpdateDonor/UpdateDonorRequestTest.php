<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\UpdateDonor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorRequest;

#[CoversClass( UpdateDonorRequest::class )]
class UpdateDonorRequestTest extends TestCase {
	public function testConstruction(): void {
		$request = UpdateDonorRequest::newInstance()
			->withSalutation( 'Miss' )
			->withTitle( 'Dr.' )
			->withFirstName( 'Pamela' )
			->withLastName( 'Isley' )
			->withCompanyName( 'Fertile Grounds Inc.' )
			->withStreetName( '123 Vine Street' )
			->withHouseNumber( '' )
			->withStreetAddress( '123 Vine Street' )
			->withPostalCode( '99887' )
			->withCity( 'Gotham' )
			->withCountryCode( 'US' )
			->withEmailAddress( 'ivy@example.com' );

		$this->assertSame( 'Miss', $request->getSalutation() );
		$this->assertSame( 'Pamela', $request->getFirstName() );
		$this->assertSame( 'Isley', $request->getLastName() );
		$this->assertSame( 'Fertile Grounds Inc.', $request->getCompanyName() );
		$this->assertSame( '123 Vine Street', $request->getStreetAddress() );
		$this->assertSame( '99887', $request->getPostalCode() );
		$this->assertSame( 'Gotham', $request->getCity() );
		$this->assertSame( 'US', $request->getCountryCode() );
		$this->assertSame( 'ivy@example.com', $request->getEmailAddress() );
	}

	public function testStringValuesAreTrimmed(): void {
		$request = UpdateDonorRequest::newInstance()
			->withSalutation( ' Miss ' )
			->withTitle( ' Dr. ' )
			->withFirstName( ' Pamela ' )
			->withLastName( ' Isley ' )
			->withCompanyName( ' Fertile Grounds Inc. ' )
			->withStreetName( '   123 Vine Street ' )
			->withHouseNumber( '  ' )
			->withStreetAddress( '   123 Vine Street ' )
			->withPostalCode( "99887\n" )
			->withCity( "\tGotham   " )
			->withCountryCode( '    US  ' )
			->withEmailAddress( 'ivy@example.com       ' );

		$this->assertSame( 'Miss', $request->getSalutation() );
		$this->assertSame( 'Pamela', $request->getFirstName() );
		$this->assertSame( 'Isley', $request->getLastName() );
		$this->assertSame( 'Fertile Grounds Inc.', $request->getCompanyName() );
		$this->assertSame( '123 Vine Street', $request->getStreetAddress() );
		$this->assertSame( '99887', $request->getPostalCode() );
		$this->assertSame( 'Gotham', $request->getCity() );
		$this->assertSame( 'US', $request->getCountryCode() );
		$this->assertSame( 'ivy@example.com', $request->getEmailAddress() );
	}

}
