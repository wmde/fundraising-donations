<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ThrowingEntityManager {

	public static function newInstance( TestCase $testCase ): EntityManager {
		$entityManager = $testCase->getMockBuilder( EntityManager::class )
			->disableOriginalConstructor()->getMock();

		$entityManager->expects( $testCase->any() )
			->method( $testCase->anything() )
			->willThrowException( new class() extends RuntimeException implements ORMException {
			} );

		return $entityManager;
	}

}
