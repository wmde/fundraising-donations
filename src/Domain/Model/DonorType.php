<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

enum DonorType {
	case PERSON;
	case COMPANY;
	case EMAIL;
	case ANONYMOUS;
}
