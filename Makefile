ci: covers phpunit cs stan

test: covers phpunit

covers:
	docker-compose run --rm app ./vendor/bin/covers-validator

phpunit:
	docker-compose run --rm app ./vendor/bin/phpunit

cs:
	docker-compose run --rm app ./vendor/bin/phpcs

stan:
	docker-compose run --rm app ./vendor/bin/phpstan analyse --level=1 --no-progress contexts/ src/ tests/

.PHONY: covers phpunit cs stan
