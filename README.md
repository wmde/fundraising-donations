Bounded Context for the Wikimedia Deutschland fundraising donation (sub-)domain.
Used by the [user facing donation application](https://github.com/wmde/FundraisingFrontend)
and the "Fundraising Operations Center" (which is not public software).

[![Build Status](https://travis-ci.org/wmde/fundraising-donations.svg?branch=master)](https://travis-ci.org/wmde/fundraising-donations)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wmde/fundraising-donations/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/wmde/fundraising-donations/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/wmde/fundraising-donations/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/wmde/fundraising-donations/?branch=master)

## Development

### Installing the dependencies

On first install, you run 

	make install-php
	
to install dependencies with composer. From time to time you should run 

	make update-php

to update the dependencies, to get the same version you'd get in CI.
	
### Running the tests

    make ci

This implicitly builds the `app` container as defined in `docker-compose.yml` & `Dockerfile`
and executes all CI checks. For commands that run only a subset, see `Makefile`.

#### PHPUnit with filter

You can run individual commands, e.g. PHPUnit with a filter, with
`docker-compose`: 

    docker-compose run --rm app ./vendor/bin/phpunit --filter valid

## Architecture

This Bounded Context follows the architecture rules outlined in [Clean Architecture + Bounded Contexts](https://www.entropywins.wtf/blog/2018/08/14/clean-architecture-bounded-contexts/).

![Architecture diagram](https://user-images.githubusercontent.com/146040/44942179-6bd68080-adac-11e8-9506-179a9470113b.png)
