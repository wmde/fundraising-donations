<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;

class CompanyContactName implements DonorName {

	public function __construct(
		private readonly string $companyName,
		private readonly string $firstName,
		private readonly string $lastName,
		private readonly string $salutation,
		private readonly string $title
	) {
	}

	public function getFullName(): string {
		$name = $this->companyName;

		if ( $this->title !== '' || $this->firstName !== '' || $this->lastName !== '' ) {
			$name .= ' - ' . implode(
					' ',
					array_filter( [
						$this->title,
						$this->firstName,
						$this->lastName
					] )
				);
		}

		return $name;
	}

	public function getSalutation(): string {
		return 'firma';
	}

	public function toArray(): array {
		return array_merge( [
			'companyName' => $this->companyName,
		],
			array_filter( [
				'salutation' => $this->salutation,
				'title' => $this->title,
				'firstName' => $this->firstName,
				'lastName' => $this->lastName
			] )
		);
	}
}
