<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

class CompanyName implements DonorName {

	private string $companyName;

	public function __construct( string $companyName ) {
		$this->companyName = $companyName;
	}

	public function toArray(): array {
		return [
			'companyName' => $this->companyName
		];
	}

	public function getFullName(): string {
		return $this->companyName;
	}

}
