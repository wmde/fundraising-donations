<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Unit\UseCases\AddDonation;

use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddDonation\ReferrerGeneralizer;

/**
 * @covers \WMDE\Fundraising\Frontend\DonationContext\UseCases\AddDonation\ReferrerGeneralizer
 *
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class ReferrerGeneralizerTest extends \PHPUnit\Framework\TestCase {

	private $domainMap = [
		'wikimedia.de' => 'wikimedia.de',
		'www.wikimedia.de' => 'wikimedia.de',
		'wikipedia.de' => 'wikipedia.de',
		'www.wikipedia.de' => 'wikipedia.de',
		'de.wikipedia.org' => 'de.wikipedia.org',
		'en.wikipedia.org' => 'en.wikipedia.org',
		'ru.wikivoyage.org' => 'ru.wikivoyage.org',
	];

	/**
	 * @dataProvider urlProvider
	 * @param string $expected
	 * @param string $url
	 */
	public function testGeneralization( string $url, string $expected ): void {
		$generalizer = new ReferrerGeneralizer( 'web', $this->domainMap );
		$this->assertSame( $expected, $generalizer->generalize( $url ) );
	}

	public function urlProvider(): array {
		return [
			[ 'http://de.wikipedia.org/wiki/Hauptseite', 'de.wikipedia.org' ],
			[ 'https://en.wikipedia.org/wiki/Main_Page', 'en.wikipedia.org' ],
			[ 'http://www.wikimedia.de/Mitarbeiter', 'wikimedia.de' ],
			[ 'https://wikimedia.de/wiki/Hauptseite', 'wikimedia.de' ],
			[ 'https://www.wikipedia.de/', 'wikipedia.de' ],
			[ 'http://www.wikipedia.de', 'wikipedia.de' ],
			[ 'wikipedia.de', 'web' ],
			[ 'https://www.google.com/?q=wikimedia+spenden', 'web' ],
			[ 'https://ru.wikivoyage.org/wiki/Молдавия', 'ru.wikivoyage.org' ],
		];
	}

}
