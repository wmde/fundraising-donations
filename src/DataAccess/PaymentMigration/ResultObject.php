<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

class ResultObject {
	private array $itemBuffer = [];
	private int $bufferIndex;
	private int $itemCount;

	// TODO
	// private BoundedValue $donationIdRange;
	// private BoundedValue $donationDateRange;

	/**
	 * @param int $bufferSize
	 * @param array $row
	 */
	public function __construct( private int $bufferSize, array $row ) {
		$this->itemBuffer = [ $row ];
		$this->bufferIndex = 0;
		$this->itemCount = 1;
		// TODO initialize from row
		//$this->donationIdRange
	}

	public function add( array $row ): void {
		$this->itemBuffer[$this->bufferIndex] = $row;
		$this->itemCount++;
		// TODO update donation and date range from row
		$this->increaseBufferIndex();
	}

	public function getItemCount(): int {
		return $this->itemCount;
	}

	public function getItemSample(): array {
		return $this->itemBuffer;
	}

	private function increaseBufferIndex(): void {
		$this->bufferIndex++;
		if ( $this->bufferIndex > $this->bufferSize ) {
			$this->bufferIndex = 0;
		}
	}
}
