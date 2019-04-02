current_user  := $(shell id -u)
current_group := $(shell id -g)
BUILD_DIR     := $(PWD)
DOCKER_FLAGS  := --interactive --tty

install-php:
	docker run --rm $(DOCKER_FLAGS) --volume $(BUILD_DIR):/app -w /app --volume /tmp:/tmp --volume ~/.composer:/composer --user $(current_user):$(current_group) composer install --ignore-platform-reqs $(COMPOSER_FLAGS)

update-php:
	docker run --rm $(DOCKER_FLAGS) --volume $(BUILD_DIR):/app -w /app --volume /tmp:/tmp --volume ~/.composer:/composer --user $(current_user):$(current_group) composer update --ignore-platform-reqs $(COMPOSER_FLAGS)

ci: covers phpunit cs stan

test: covers phpunit

covers:
	docker-compose run --rm app ./vendor/bin/covers-validator

phpunit:
	docker-compose run --rm app ./vendor/bin/phpunit

cs:
	docker-compose run --rm app ./vendor/bin/phpcs

stan:
	docker-compose run --rm app ./vendor/bin/phpstan analyse --level=1 --no-progress src/ tests/

.PHONY: covers phpunit cs stan
