<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

class ResultObject {
	private array $itemBuffer = [];
	private int $bufferIndex;
	private int $itemCount;
	private BoundedValue $donationIdRange;
	private BoundedValue $donationDateRange;

	/**
	 * @param int $bufferSize
	 * @param array $row
	 */
	public function __construct( private int $bufferSize, array $row ) {
		$this->itemBuffer = [ $row ];
		$this->bufferIndex = 0;
		$this->itemCount = 1;
		$this->donationIdRange = new BoundedValue( $row['id'] );
		$this->donationDateRange = new BoundedValue( $row['donationDate'] );
	}

	public function add( array $row ): void {
		$this->itemBuffer[$this->bufferIndex] = $row;
		$this->itemCount++;
		$this->donationIdRange->set( $row['id'] );
		$this->donationDateRange->set( $row['donationDate'] );
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

	public function getDonationIdRange(): BoundedValue {
		return $this->donationIdRange;
	}

	public function getDonationDateRange(): BoundedValue {
		return $this->donationDateRange;
	}

}