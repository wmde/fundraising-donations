<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;

class PersonName implements DonorName {

	private string $salutation;
	private string $title;
	private string $firstName;
	private string $lastName;

	public function __construct( string $firstName, string $lastName, string $salutation, string $title ) {
		$this->salutation = $salutation;
		$this->title = $title;
		$this->firstName = $firstName;
		$this->lastName = $lastName;
	}

	public function toArray(): array {
		return [
			'salutation' => $this->salutation,
			'title' => $this->title,
			'firstName' => $this->firstName,
			'lastName' => $this->lastName
		];
	}

	public function getFullName(): string {
		return implode(
			' ',
			array_filter(
				[
					$this->title,
					$this->firstName,
					$this->lastName
				]
			)
		);
	}

}
