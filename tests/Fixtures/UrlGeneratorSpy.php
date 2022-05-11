<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\PaymentContext\Domain\PaymentUrlGenerator\PaymentProviderURLGenerator;
use WMDE\Fundraising\PaymentContext\Domain\PaymentUrlGenerator\RequestContext;

class UrlGeneratorSpy implements PaymentProviderURLGenerator {
	private RequestContext|null $lastContext = null;

	public function generateURL( RequestContext $requestContext ): string {
		if ( $this->lastContext !== null ) {
			throw new \LogicException( 'Url generator must only be called once' );
		}
		$this->lastContext = $requestContext;
		// Adding context just to make the url parameter look impressive and different from a stub
		return 'https://example.com/?context=' . urlencode( var_export( $requestContext, true ) );
	}

	public function getLastContext(): RequestContext {
		if ( $this->lastContext === null ) {
			throw new \LogicException( 'Url generator should have been called' );
		}
		return $this->lastContext;
	}
}
