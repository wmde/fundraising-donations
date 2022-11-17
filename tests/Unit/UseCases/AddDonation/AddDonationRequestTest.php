<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest
 */
class AddDonationRequestTest extends TestCase {
	public function testDonorFieldsAreEmptyByDefault(): void {
		$request = new AddDonationRequest();

		$this->assertSame( '', $request->getDonorSalutation() );
		$this->assertSame( '', $request->getDonorTitle() );
		$this->assertSame( '', $request->getDonorFirstName() );
		$this->assertSame( '', $request->getDonorLastName() );
		$this->assertSame( '', $request->getDonorCompany() );
		$this->assertSame( '', $request->getDonorStreetAddress() );
		$this->assertSame( '', $request->getDonorPostalCode() );
		$this->assertSame( '', $request->getDonorCity() );
		$this->assertSame( '', $request->getDonorEmailAddress() );
	}

	public function testDonorFieldGettersAndSetters(): void {
		$request = new AddDonationRequest();
		$request->setDonorSalutation( 'Herr' );
		$request->setDonorTitle( 'Dr.' );
		$request->setDonorFirstName( 'Bruce' );
		$request->setDonorLastName( 'Wayne' );
		$request->setDonorCompany( 'Wayne Enterprises' );
		$request->setDonorPostalCode( '66484' );
		$request->setDonorStreetAddress( 'Fledergasse 9' );
		$request->setDonorCity( 'Battweiler' );
		$request->setDonorCountryCode( 'ZZ' );
		$request->setDonorEmailAddress( 'bw@waynecorp.biz' );
		$request->setDonorType( DonorType::PERSON() );
		$anonymousRequest = new AddDonationRequest();
		$anonymousRequest->setDonorType( DonorType::ANONYMOUS() );

		$this->assertSame( 'Herr', $request->getDonorSalutation() );
		$this->assertSame( 'Dr.', $request->getDonorTitle() );
		$this->assertSame( 'Bruce', $request->getDonorFirstName() );
		$this->assertSame( 'Wayne', $request->getDonorLastName() );
		$this->assertSame( 'Wayne Enterprises', $request->getDonorCompany() );
		$this->assertSame( 'Fledergasse 9', $request->getDonorStreetAddress() );
		$this->assertSame( '66484', $request->getDonorPostalCode() );
		$this->assertSame( 'Battweiler', $request->getDonorCity() );
		$this->assertSame( 'ZZ', $request->getDonorCountryCode() );
		$this->assertSame( 'bw@waynecorp.biz', $request->getDonorEmailAddress() );
		$this->assertEquals( DonorType::PERSON(), $request->getDonorType() );
		$this->assertFalse( $request->donorIsAnonymous() );
		$this->assertTrue( $anonymousRequest->donorIsAnonymous() );
	}

	public function testPaymentRequestDefaultValues(): void {
		$request = new AddDonationRequest();

		$paymentCreationRequest = $request->getPaymentCreationRequest();

		$this->assertSame( 0, $paymentCreationRequest->amountInEuroCents );
		$this->assertSame( PaymentInterval::OneTime->value, $paymentCreationRequest->interval );
		$this->assertSame( '', $paymentCreationRequest->paymentType );
		$this->assertSame( '', $paymentCreationRequest->iban );
		$this->assertSame( '', $paymentCreationRequest->bic );
		$this->assertSame( '', $paymentCreationRequest->transferCodePrefix );
	}

	public function testPaymentRequestGetterAndSetter(): void {
		$paymentCreationRequest = new PaymentCreationRequest(
			100,
			PaymentInterval::OneTime->value,
			'BEZ'
		);
		$request = new AddDonationRequest();
		$request->setPaymentCreationRequest( $paymentCreationRequest );

		$this->assertSame( $paymentCreationRequest, $request->getPaymentCreationRequest() );
	}

	public function testTrackingFields(): void {
		$request = new AddDonationRequest();
		$request->setTracking( 'test_campaign/test_keyword' );
		$request->setSingleBannerImpressionCount( 4 );
		$request->setTotalImpressionCount( 10 );

		$this->assertSame( 'test_campaign/test_keyword', $request->getTracking() );
		$this->assertSame( 10, $request->getTotalImpressionCount() );
		$this->assertSame( 4, $request->getSingleBannerImpressionCount() );
	}

	public function testOptIn(): void {
		$request = new AddDonationRequest();
		$request->setOptsIntoNewsletter( true );
		$request->setOptsIntoDonationReceipt( true );

		$this->assertTrue( $request->getOptsIntoNewsletter() );
		$this->assertTrue( $request->getOptsIntoDonationReceipt() );
	}

	public function testStringFieldsGetTrimmed(): void {
		$request = new AddDonationRequest();
		$request->setDonorSalutation( ' Herr  ' );
		$request->setDonorTitle( '  Dr. ' );
		$request->setDonorFirstName( '  Bruce ' );
		$request->setDonorLastName( ' Wayne ' );
		$request->setDonorCompany( "Wayne Enterprises\n" );
		$request->setDonorPostalCode( '66484   ' );
		$request->setDonorStreetAddress( ' Fledergasse 9   ' );
		$request->setDonorCity( ' Battweiler ' );
		$request->setDonorCountryCode( ' ZZ ' );
		$request->setDonorEmailAddress( ' bw@waynecorp.biz        ' );
		$request->setPaymentType( ' BTC   ' );
		$request->setTracking( ' test_campaign/test_keyword ' );
		$request->setOptIn( '    1' );

		$this->assertSame( 'Herr', $request->getDonorSalutation() );
		$this->assertSame( 'Dr.', $request->getDonorTitle() );
		$this->assertSame( 'Bruce', $request->getDonorFirstName() );
		$this->assertSame( 'Wayne', $request->getDonorLastName() );
		$this->assertSame( 'Wayne Enterprises', $request->getDonorCompany() );
		$this->assertSame( 'Fledergasse 9', $request->getDonorStreetAddress() );
		$this->assertSame( '66484', $request->getDonorPostalCode() );
		$this->assertSame( 'Battweiler', $request->getDonorCity() );
		$this->assertSame( 'ZZ', $request->getDonorCountryCode() );
		$this->assertSame( 'bw@waynecorp.biz', $request->getDonorEmailAddress() );
		$this->assertSame( 'test_campaign/test_keyword', $request->getTracking() );
		$this->assertTrue( $request->getOptsIntoNewsletter() );
	}

}
