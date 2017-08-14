EXPERIMENTAL (such T4) extract of the Donation Context out of the FundraisingFrontend git repo.

[![Build Status](https://travis-ci.org/wmde/fundraising-donations.svg?branch=master)](https://travis-ci.org/wmde/fundraising-donations)

## Development

### Install dependencies

    docker run -it --rm --user $(id -u):$(id -g) -v ~/.composer:/composer -v $(pwd):/app docker.io/composer

### Build application container

    docker build -t wmde/fundraising-donations .

### Run tests

    docker run -it --rm -v "$PWD":/usr/src/myapp -w /usr/src/myapp wmde/fundraising-donations ./vendor/bin/phpunit
