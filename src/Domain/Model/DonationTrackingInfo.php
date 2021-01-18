<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

use WMDE\FreezableValueObject\FreezableValueObject;

/**
 * TODO: move to Infrastructure
 *
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DonationTrackingInfo {
	use FreezableValueObject;

	private string $tracking;
	private string $source = '';
	private int $totalImpressionCount;
	private int $singleBannerImpressionCount;
	private string $color = '';
	private string $skin = '';
	private string $layout = '';

	private function __construct() {
	}

	public function getTracking(): string {
		return $this->tracking;
	}

	public function setTracking( string $tracking ): void {
		$this->assertIsWritable();
		$this->tracking = $tracking;
	}

	/**
	 * @deprecated See https://phabricator.wikimedia.org/T134327
	 * @return string
	 */
	public function getSource(): string {
		return $this->source;
	}

	/**
	 * @deprecated See https://phabricator.wikimedia.org/T134327
	 * @param string $source
	 */
	public function setSource( string $source ): void {
		$this->assertIsWritable();
		$this->source = $source;
	}

	public function getTotalImpressionCount(): int {
		return $this->totalImpressionCount;
	}

	public function setTotalImpressionCount( int $totalImpressionCount ): void {
		$this->assertIsWritable();
		$this->totalImpressionCount = $totalImpressionCount;
	}

	public function getSingleBannerImpressionCount(): int {
		return $this->singleBannerImpressionCount;
	}

	public function setSingleBannerImpressionCount( int $singleBannerImpressionCount ): void {
		$this->assertIsWritable();
		$this->singleBannerImpressionCount = $singleBannerImpressionCount;
	}

	/**
	 * @deprecated See https://phabricator.wikimedia.org/T134327
	 * @return string
	 */
	public function getColor(): string {
		return $this->color;
	}

	/**
	 * @deprecated See https://phabricator.wikimedia.org/T134327
	 * @param string $color
	 */
	public function setColor( string $color ): void {
		$this->assertIsWritable();
		$this->color = $color;
	}

	public function getSkin(): string {
		return $this->skin;
	}

	/**
	 * @deprecated See https://phabricator.wikimedia.org/T134327
	 * @param string $skin
	 */
	public function setSkin( string $skin ): void {
		$this->assertIsWritable();
		$this->skin = $skin;
	}

	/**
	 * @deprecated See https://phabricator.wikimedia.org/T134327
	 * @return string
	 */
	public function getLayout(): string {
		return $this->layout;
	}

	/**
	 * @deprecated See https://phabricator.wikimedia.org/T134327
	 * @param string $layout
	 */
	public function setLayout( string $layout ): void {
		$this->assertIsWritable();
		$this->layout = $layout;
	}

	public static function newBlankTrackingInfo(): self {
		$trackingInfo = new self();
		$trackingInfo->setColor( '' );
		$trackingInfo->setLayout( '' );
		$trackingInfo->setSingleBannerImpressionCount( 0 );
		$trackingInfo->setSkin( '' );
		$trackingInfo->setSource( '' );
		$trackingInfo->setTotalImpressionCount( 0 );
		$trackingInfo->setTracking( '' );

		return $trackingInfo;
	}

}
