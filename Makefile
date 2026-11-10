.DEFAULT_GOAL := help
SHELL := /bin/sh

-include .env

COMPOSE := docker compose
EXEC    := $(COMPOSE) exec -T app
PHP     := $(EXEC) php

FORWARD_APP_PORT          ?= 8000
FORWARD_DB_PORT           ?= 3306
FORWARD_REDIS_PORT        ?= 6379
FORWARD_RABBITMQ_PORT     ?= 5672
FORWARD_RABBITMQ_UI_PORT  ?= 15672
FORWARD_MAILPIT_SMTP_PORT ?= 1025
FORWARD_MAILPIT_UI_PORT   ?= 8025

SERVICES := app mysql redis rabbitmq mailpit

##@ Help

.PHONY: help
help: ## List every target by section
	@awk 'BEGIN {FS = ":.*##"} \
		/^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } \
		/^[a-zA-Z0-9_-]+:.*##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

##@ Environment

.PHONY: build up down restart logs ps urls
build: ## Rebuild the app image (after Dockerfile or PHP config changes)
	$(COMPOSE) build app

up: ## Start every service and wait until healthy
	$(COMPOSE) up -d --wait

down: ## Stop and remove the containers (volumes are kept)
	$(COMPOSE) down

restart: ## Restart every service
	$(COMPOSE) restart
	$(COMPOSE) up -d --wait

logs: ## Follow logs (S=<service> for a single one)
	$(COMPOSE) logs -f --tail=100 $(S)

ps: ## Show container status and health
	$(COMPOSE) ps

urls: ## Show container IP, host port and URL of each service
	@printf "\033[1m%-10s %-16s %-18s %s\033[0m\n" SERVICE "CONTAINER IP" "HOST" "URL"
	@for service in $(SERVICES); do \
		id=$$($(COMPOSE) ps -q $$service 2>/dev/null); \
		ip=$$( [ -n "$$id" ] && docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}' $$id | xargs || echo "not running"); \
		case $$service in \
			app)      host="localhost:$(FORWARD_APP_PORT)";   url="http://localhost:$(FORWARD_APP_PORT)/health/ready" ;; \
			mysql)    host="localhost:$(FORWARD_DB_PORT)";    url="mysql://localhost:$(FORWARD_DB_PORT)/$(DB_DATABASE)" ;; \
			redis)    host="localhost:$(FORWARD_REDIS_PORT)"; url="redis://localhost:$(FORWARD_REDIS_PORT)" ;; \
			rabbitmq) host="localhost:$(FORWARD_RABBITMQ_PORT)"; url="http://localhost:$(FORWARD_RABBITMQ_UI_PORT) (management UI)" ;; \
			mailpit)  host="localhost:$(FORWARD_MAILPIT_SMTP_PORT)"; url="http://localhost:$(FORWARD_MAILPIT_UI_PORT) (web UI)" ;; \
		esac; \
		printf "%-10s %-16s %-18s %s\n" $$service "$${ip:-not running}" "$$host" "$$url"; \
	done

##@ Setup

.PHONY: setup shell composer
setup: ## First run: .env, build, start, dependencies, app key, migrations, git hooks
	@[ -f .env ] || { cp .env.example .env; sed -i "s/^UID=.*/UID=$$(id -u)/; s/^GID=.*/GID=$$(id -g)/" .env; echo ".env created from .env.example"; }
	@docker image inspect travel-request-service/app:dev >/dev/null 2>&1 || $(COMPOSE) build app
	$(COMPOSE) up -d --wait mysql redis rabbitmq mailpit
	$(COMPOSE) run --rm --no-deps app composer install --no-interaction
	@grep -q '^APP_KEY=base64' .env || $(COMPOSE) run --rm --no-deps app php artisan key:generate --ansi
	$(COMPOSE) up -d --wait
	$(PHP) artisan migrate --force
	$(EXEC) vendor/bin/captainhook install --only-enabled --force --no-interaction

shell: ## Open a shell in the app container
	$(COMPOSE) exec app sh

composer: ## Run composer in the app container (ARGS="require foo/bar")
	$(EXEC) composer $(ARGS)

##@ Quality

.PHONY: lint lint-fix stan arch qa
lint: ## Check code style (Pint, PER)
	$(EXEC) vendor/bin/pint --test

lint-fix: ## Fix code style (Pint, PER)
	$(EXEC) vendor/bin/pint

stan: ## Static analysis (PHPStan + Larastan, level max)
	$(EXEC) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

arch: ## Architecture rules (Deptrac: layers and contexts)
	$(EXEC) vendor/bin/deptrac analyse --config-file=deptrac.layers.yaml --no-progress
	$(EXEC) vendor/bin/deptrac analyse --config-file=deptrac.contexts.yaml --no-progress

qa: lint stan arch coverage infection ## Everything CI runs: lint, stan, arch, all suites with coverage, infection

##@ Tests

.PHONY: test test-unit test-integration test-feature test-tools test-e2e coverage infection
test: ## Run every suite
	$(PHP) vendor/bin/phpunit

test-unit: ## Unit suite (Domain + Application)
	$(PHP) vendor/bin/phpunit --testsuite=unit --testdox

test-integration: ## Integration suite (real MySQL, Redis, RabbitMQ)
	$(PHP) vendor/bin/phpunit --testsuite=integration

test-feature: ## Feature suite (HTTP)
	$(PHP) vendor/bin/phpunit --testsuite=feature

test-tools: ## Tests of the in-repo tooling (custom PHPStan rules)
	$(PHP) vendor/bin/phpunit --testsuite=tools

test-e2e: ## End-to-end suite (against the running Octane server)
	$(PHP) vendor/bin/phpunit --testsuite=e2e

coverage: ## Every suite with line coverage; fails below 85%
	$(PHP) vendor/bin/phpunit --coverage-clover=build/coverage/clover.xml --coverage-html=build/coverage/html --coverage-text --only-summary-for-coverage-text
	$(PHP) tools/check-coverage.php build/coverage/clover.xml 85

infection: ## Mutation testing on Domain + Application (MSI >= 90%, covered MSI >= 95%)
	$(EXEC) vendor/bin/infection --no-progress --show-mutations

##@ Not implemented yet

.PHONY: users load-smoke load-load load-stress load-concurrency obs web
users: ## Create a user or an admin (not implemented yet)
	@echo "make $@: not implemented yet"

load-smoke: ## Load test: smoke (1 VU) (not implemented yet)
	@echo "make $@: not implemented yet"

load-load: ## Load test: expected load (not implemented yet)
	@echo "make $@: not implemented yet"

load-stress: ## Load test: ramp until it breaks (not implemented yet)
	@echo "make $@: not implemented yet"

load-concurrency: ## Load test: concurrent approve/cancel on the same request (not implemented yet)
	@echo "make $@: not implemented yet"

obs: ## Start the observability profile (not implemented yet)
	@echo "make $@: not implemented yet"

web: ## Start the optional frontend profile (not implemented yet)
	@echo "make $@: not implemented yet"
