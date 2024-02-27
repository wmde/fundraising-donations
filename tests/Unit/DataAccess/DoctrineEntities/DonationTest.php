<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\DoctrineEntities;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\DataAccess\DonationData;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DonationData
 */
class DonationTest extends TestCase {

	public function testDataEncodingAndDecodingRoundtrips(): void {
		$donation = new Donation();

		$someData = [
			'nyan' => 'cat',
			'foo' => null,
			'bar' => 9000.01,
			'baz' => [ true ]
		];

		$donation->encodeAndSetData( $someData );

		$this->assertSame( $someData, $donation->getDecodedData() );
	}

	public function testGivenNoData_getDecodedDataReturnsEmptyArray(): void {
		$donation = new Donation();

		$this->assertSame( [], $donation->getDecodedData() );
	}

	public function testWhenSettingIdToAnInteger_getIdReturnsIt(): void {
		$donation = new Donation();
		$donation->setId( 1337 );

		$this->assertSame( 1337, $donation->getId() );
	}

	public function testWhenSettingIdToNull_getIdReturnsNull(): void {
		$donation = new Donation();
		$donation->setId( 1337 );
		$donation->setId( null );

		$this->assertNull( $donation->getId() );
	}

	public function testWhenIdIsNotSet_getIdReturnsNull(): void {
		$donation = new Donation();

		$this->assertNull( $donation->getId() );
	}

	public function testGivenNoData_getDataObjectReturnsObjectWithNullValues(): void {
		$donation = new Donation();

		$this->assertNull( $donation->getDataObject()->getAccessToken() );
		$this->assertNull( $donation->getDataObject()->getUpdateToken() );
		$this->assertNull( $donation->getDataObject()->getUpdateTokenExpiry() );
	}

	public function testGivenData_getDataObjectReturnsTheValues(): void {
		$donation = new Donation();
		$donation->encodeAndSetData( [
			'token' => 'foo',
			'utoken' => 'bar',
			'uexpiry' => 'baz',
		] );

		$this->assertSame( 'foo', $donation->getDataObject()->getAccessToken() );
		$this->assertSame( 'bar', $donation->getDataObject()->getUpdateToken() );
		$this->assertSame( 'baz', $donation->getDataObject()->getUpdateTokenExpiry() );
	}

	public function testWhenProvidingData_setDataObjectSetsData(): void {
		$data = new DonationData();
		$data->setAccessToken( 'foo' );
		$data->setUpdateToken( 'bar' );
		$data->setUpdateTokenExpiry( 'baz' );

		$donation = new Donation();
		$donation->setDataObject( $data );

		$this->assertSame(
			[
				'token' => 'foo',
				'utoken' => 'bar',
				'uexpiry' => 'baz',
			],
			$donation->getDecodedData()
		);
	}

	public function testWhenProvidingNullData_setObjectDoesNotSetFields(): void {
		$donation = new Donation();
		$donation->setDataObject( new DonationData() );

		$this->assertSame(
			[],
			$donation->getDecodedData()
		);
	}

	public function testWhenDataAlreadyExists_setDataObjectRetainsAndUpdatesData(): void {
		$donation = new Donation();
		$donation->encodeAndSetData( [
			'nyan' => 'cat',
			'token' => 'wee',
			'pink' => 'fluffy',
		] );

		$data = new DonationData();
		$data->setAccessToken( 'foo' );
		$data->setUpdateToken( 'bar' );

		$donation->setDataObject( $data );

		$this->assertSame(
			[
				'nyan' => 'cat',
				'token' => 'foo',
				'pink' => 'fluffy',
				'utoken' => 'bar',
			],
			$donation->getDecodedData()
		);
	}

	public function testWhenModifyingTheDataObject_modificationsAreReflected(): void {
		$donation = new Donation();
		$donation->encodeAndSetData( [
			'nyan' => 'cat',
			'token' => 'wee',
			'pink' => 'fluffy',
		] );

		$donation->modifyDataObject( static function ( DonationData $data ) {
			$data->setAccessToken( 'foo' );
			$data->setUpdateToken( 'bar' );
		} );

		$this->assertSame(
			[
				'nyan' => 'cat',
				'token' => 'foo',
				'pink' => 'fluffy',
				'utoken' => 'bar',
			],
			$donation->getDecodedData()
		);
	}

	public function testStatusConstantsExist(): void {
		$this->assertNotNull( Donation::STATUS_NEW );
		$this->assertNotNull( Donation::STATUS_CANCELLED );
		$this->assertNotNull( Donation::STATUS_EXTERNAL_BOOKED );
		$this->assertNotNull( Donation::STATUS_EXTERNAL_INCOMPLETE );
		$this->assertNotNull( Donation::STATUS_MODERATION );
		$this->assertNotNull( Donation::STATUS_PROMISE );
		$this->assertNotNull( Donation::STATUS_EXPORTED );
	}

}
