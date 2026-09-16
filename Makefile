# set all to phony
SHELL=bash

.DEFAULT_GOAL := all

.PHONY: *

PHP_VERSION="8.5"
NODE_VERSION="22"
CONTAINER_NAME=$(shell echo "ghcr.io/wyrihaximusnet/php:${PHP_VERSION}-nts-alpine-dev")
NODE_CONTAINER_NAME=$(shell echo "node:${NODE_VERSION}-alpine")
COMPOSER_CACHE_DIR=$(shell composer config --global cache-dir -q || echo ${HOME}/.composer-php/cache)
COMPOSER_CONTAINER_CACHE_DIR=$(shell docker run --rm -it ${CONTAINER_NAME} composer config --global cache-dir -q || echo ${HOME}/.composer-php/cache)
RENOVATE_PRESETS=\
	default.json \
	docker-image.json \
	github-action.json \
	php-package-dev.json \
	php-package-next.json \
	php-package.json \
	php-project-self-hosted-runner.json \
	php-project.json

ifneq ("$(wildcard /.you-are-in-a-wyrihaximus.net-php-docker-image)","")
    IN_DOCKER=TRUE
else
    IN_DOCKER=FALSE
endif

ifeq ("$(IN_DOCKER)","TRUE")
	DOCKER_RUN:=
	NODE_DOCKER_RUN:=
else
	DOCKER_RUN:=docker run --rm -t \
		-v "`pwd`:`pwd`" \
		-v "${COMPOSER_CACHE_DIR}:${COMPOSER_CONTAINER_CACHE_DIR}" \
		-w "`pwd`" \
		${CONTAINER_NAME}
	NODE_DOCKER_RUN:=docker run --rm -t \
		-v "`pwd`:`pwd`" \
		-w "`pwd`" \
		${NODE_CONTAINER_NAME}
endif

generate-readme: ## Generate README.md with docbot ####
	$(DOCKER_RUN) sh -c 'cd etc && ../vendor/bin/docbot execute'

generate: install generate-readme

after-renovate: generate ## Tasks to run after Renovate updates dependencies ####

renovate-config-validator: ## Validate Renovate preset JSON files ####
	$(NODE_DOCKER_RUN) npx --yes --package renovate -- renovate-config-validator --strict --no-global $(RENOVATE_PRESETS)

ensure-readme-is-up-to-date: ## Ensure README.md matches generated output ####
	cp README.md README.current.md
	$(MAKE) generate-readme
	diff -u README.current.md README.md
	rm README.current.md

all: renovate-config-validator ensure-readme-is-up-to-date ## Run local CI checks ####

task-list-ci: ## CI: Generate a JSON array of jobs to run ####
	@echo '["renovate-config-validator","ensure-readme-is-up-to-date"]'

shell: ## Provides Shell access in the expected environment ####
	$(DOCKER_RUN) bash

install: ## Install dependencies ####
	$(DOCKER_RUN) composer install

update: ## Update dependencies ####
	$(DOCKER_RUN) composer update -W

outdated: ## Show outdated dependencies ####
	$(DOCKER_RUN) composer outdated
