.DEFAULT_GOAL := help
SHELL := /bin/sh

-include .env

COMPOSE := docker compose
EXEC    := $(COMPOSE) exec -T app
PHP     := $(EXEC) php

FORWARD_APP_PORT          ?= 8000
FORWARD_DB_PORT           ?= 3306
FORWARD_DB_REPLICA_PORT   ?= 3307
FORWARD_REDIS_PORT        ?= 6379
FORWARD_RABBITMQ_PORT     ?= 5672
FORWARD_RABBITMQ_UI_PORT  ?= 15672
FORWARD_MAILPIT_SMTP_PORT ?= 1025
FORWARD_MAILPIT_UI_PORT   ?= 8025
FORWARD_REVERB_PORT       ?= 8080
FORWARD_GRAFANA_PORT      ?= 3000
FORWARD_JAEGER_UI_PORT    ?= 16686
FORWARD_PROMETHEUS_PORT   ?= 9090
FORWARD_LOKI_PORT         ?= 3100
FORWARD_LIKEC4_PORT       ?= 5173

OBS_SERVICES := otel-collector jaeger prometheus loki grafana cadvisor

SERVICES := app outbox-relay notification-consumer reverb mysql mysql-replica redis rabbitmq mailpit grafana jaeger prometheus loki likec4

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

up: ## Start the default profile and wait until healthy
	$(COMPOSE) up -d --wait

down: ## Stop and remove every container of every profile (volumes are kept)
	$(COMPOSE) --profile "*" down

restart: ## Restart every service
	$(COMPOSE) restart
	$(COMPOSE) up -d --wait

logs: ## Follow logs (S=<service> for a single one)
	$(COMPOSE) logs -f --tail=100 $(S)

ps: ## Show container status and health
	$(COMPOSE) ps

urls: ## Show container IP, host port and URL of each service
	@printf "\033[1m%-22s %-16s %-18s %s\033[0m\n" SERVICE "CONTAINER IP" "HOST" "URL"
	@for service in $(SERVICES); do \
		id=$$($(COMPOSE) ps -q $$service 2>/dev/null); \
		ip=$$( [ -n "$$id" ] && docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}' $$id | xargs || echo "not running"); \
		case $$service in \
			app)      host="localhost:$(FORWARD_APP_PORT)";   url="http://localhost:$(FORWARD_APP_PORT)/health/ready" ;; \
			mysql)    host="localhost:$(FORWARD_DB_PORT)";    url="mysql://localhost:$(FORWARD_DB_PORT)/$(DB_DATABASE) (primary)" ;; \
			mysql-replica) host="localhost:$(FORWARD_DB_REPLICA_PORT)"; url="mysql://localhost:$(FORWARD_DB_REPLICA_PORT)/$(DB_DATABASE) (read replica)" ;; \
			redis)    host="localhost:$(FORWARD_REDIS_PORT)"; url="redis://localhost:$(FORWARD_REDIS_PORT)" ;; \
			rabbitmq) host="localhost:$(FORWARD_RABBITMQ_PORT)"; url="http://localhost:$(FORWARD_RABBITMQ_UI_PORT) (management UI)" ;; \
			mailpit)  host="localhost:$(FORWARD_MAILPIT_SMTP_PORT)"; url="http://localhost:$(FORWARD_MAILPIT_UI_PORT) (web UI)" ;; \
			outbox-relay) host="-"; url="no port (make relay-logs)" ;; \
			notification-consumer) host="-"; url="no port (make consumer-logs)" ;; \
			reverb)   host="localhost:$(FORWARD_REVERB_PORT)"; url="http://localhost:$(FORWARD_APP_PORT)/docs/notifications (live page, APP_ENV=local)" ;; \
			grafana)  host="localhost:$(FORWARD_GRAFANA_PORT)"; url="http://localhost:$(FORWARD_GRAFANA_PORT) (obs profile, anonymous viewer)" ;; \
			jaeger)   host="localhost:$(FORWARD_JAEGER_UI_PORT)"; url="http://localhost:$(FORWARD_JAEGER_UI_PORT) (obs profile)" ;; \
			prometheus) host="localhost:$(FORWARD_PROMETHEUS_PORT)"; url="http://localhost:$(FORWARD_PROMETHEUS_PORT) (obs profile)" ;; \
			loki)     host="localhost:$(FORWARD_LOKI_PORT)"; url="http://localhost:$(FORWARD_LOKI_PORT)/ready (obs profile)" ;; \
			likec4)   host="localhost:$(FORWARD_LIKEC4_PORT)"; url="http://localhost:$(FORWARD_LIKEC4_PORT) (docs profile, C4 diagrams)" ;; \
		esac; \
		printf "%-22s %-16s %-18s %s\n" $$service "$${ip:-not running}" "$$host" "$$url"; \
	done

##@ Setup

.PHONY: setup shell composer
setup: ## First run: .env, build, start, dependencies, app key, migrations, JWT keys, git hooks
	@[ -f .env ] || { cp .env.example .env; sed -i "s/^UID=.*/UID=$$(id -u)/; s/^GID=.*/GID=$$(id -g)/" .env; echo ".env created from .env.example"; }
	@docker image inspect travel-request-service/app:dev >/dev/null 2>&1 || $(COMPOSE) build app
	$(COMPOSE) up -d --wait mysql mysql-replica redis rabbitmq mailpit
	$(COMPOSE) run --rm --no-deps app composer install --no-interaction
	@grep -q '^APP_KEY=base64' .env || $(COMPOSE) run --rm --no-deps app php artisan key:generate --ansi
	@$(MAKE) --no-print-directory db-replica-sync
	$(COMPOSE) up -d --wait
	$(PHP) artisan migrate --force
	$(PHP) artisan identity:keys:generate
	$(EXEC) vendor/bin/captainhook install --only-enabled --force --no-interaction

db-replica-sync: ## Rebuild the read replica from the primary and start replication (first run, or after it breaks)
	COMPOSE="$(COMPOSE)" DB_ROOT_PASSWORD="$(DB_ROOT_PASSWORD)" DB_DATABASE="$(DB_DATABASE)" DB_USERNAME="$(DB_USERNAME)" \
		DB_PASSWORD="$(DB_PASSWORD)" DB_REPLICATION_PASSWORD="$(DB_REPLICATION_PASSWORD)" sh docker/mysql/sync-replica.sh

db-replica-status: ## Show whether the replica is replicating and how far behind it is
	@$(COMPOSE) exec -T mysql-replica sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" -e "SHOW REPLICA STATUS\G" 2>/dev/null' \
		| grep -E 'Replica_IO_Running|Replica_SQL_Running|Seconds_Behind_Source|Last_.*Error:'

shell: ## Open a shell in the app container
	$(COMPOSE) exec app sh

composer: ## Run composer in the app container (ARGS="require foo/bar")
	$(EXEC) composer $(ARGS)

##@ Identity

.PHONY: keys keys-rotate user admin seed
keys: ## Generate the JWT signing keys if missing (storage/keys, outside git)
	$(PHP) artisan identity:keys:generate

keys-rotate: ## Replace the JWT signing keys (every issued access token stops working)
	$(PHP) artisan identity:keys:generate --force

user: ## Create a regular user (NAME="..." EMAIL=... PASSWORD=...; asks for what is missing)
	$(COMPOSE) exec app php artisan identity:create-user $(if $(NAME),--name="$(NAME)") $(if $(EMAIL),--email="$(EMAIL)") $(if $(PASSWORD),--password="$(PASSWORD)")

admin: ## Create an admin (NAME="..." EMAIL=... PASSWORD=...; asks for what is missing)
	$(COMPOSE) exec app php artisan identity:create-admin $(if $(NAME),--name="$(NAME)") $(if $(EMAIL),--email="$(EMAIL)") $(if $(PASSWORD),--password="$(PASSWORD)")

seed: ## Seed the demo users: admin@, luffy@ and zoro@example.com (password: password123)
	$(PHP) artisan db:seed --class='Infrastructure\Identity\Seeder\DemoUsersSeeder' --force

.PHONY: demo
demo: ## Walk the whole flow: Luffy asks for a trip, the admin approves it, the notification and the e-mail arrive
	@$(COMPOSE) exec -T -e DEMO_APP_URL=http://localhost:$(FORWARD_APP_PORT) -e DEMO_MAILPIT_URL=http://localhost:$(FORWARD_MAILPIT_UI_PORT) app php tools/demo.php

##@ API

.PHONY: openapi
openapi: ## Export the OpenAPI document to docs/api/openapi.json and print the docs URL (open only with APP_ENV=local)
	$(PHP) artisan scramble:export
	@echo "API docs: http://localhost:$(FORWARD_APP_PORT)/docs/api  (OpenAPI JSON: /docs/api.json)"

##@ Messaging

.PHONY: events outbox relay-logs
events: ## Print the travel request events on the inspection queue (the first run creates the queue; LIMIT=20)
	$(PHP) artisan messaging:tap --limit=$(or $(LIMIT),20)

outbox: ## Show how many outbox messages are pending and published
	$(PHP) artisan outbox:status

relay-logs: ## Follow the outbox relay logs
	$(COMPOSE) logs -f --tail=100 outbox-relay

##@ Notifications

.PHONY: dlq dlq-replay consumer-logs reverb-logs mailpit
dlq: ## Print the dead-lettered decisions with their reason, keeping them on the queue
	$(PHP) artisan notification:dlq

dlq-replay: ## Send every dead-lettered decision back to the notification queue
	$(PHP) artisan notification:dlq --replay

consumer-logs: ## Follow the notification consumer logs
	$(COMPOSE) logs -f --tail=100 notification-consumer

reverb-logs: ## Follow the Reverb server logs (runs with --debug)
	$(COMPOSE) logs -f --tail=100 reverb

mailpit: ## Print the Mailpit web UI URL
	@echo "Mailpit: http://localhost:$(FORWARD_MAILPIT_UI_PORT)"

##@ Observability

.PHONY: obs obs-measure obs-down obs-urls
obs: ## Start the obs profile (Collector, Jaeger, Prometheus, Loki, Grafana, cAdvisor) and turn tracing on in app, relay and consumer
	OTEL_SDK_DISABLED=false $(COMPOSE) --profile obs up -d --wait
	@$(MAKE) --no-print-directory obs-urls

obs-measure: ## Start the obs profile with the app SDK off: only k6 and container metrics, for load measurements
	OTEL_SDK_DISABLED=true $(COMPOSE) --profile obs up -d --wait
	@$(MAKE) --no-print-directory obs-urls

obs-down: ## Stop the obs profile and turn tracing off again
	$(COMPOSE) --profile obs rm -sf $(OBS_SERVICES)
	OTEL_SDK_DISABLED=true $(COMPOSE) up -d --wait

obs-urls: ## Print the URLs of Grafana, Jaeger, Prometheus and Loki
	@echo "Grafana:    http://localhost:$(FORWARD_GRAFANA_PORT)  (dashboard Travel Request Service; admin login in .env)"
	@echo "Jaeger:     http://localhost:$(FORWARD_JAEGER_UI_PORT)"
	@echo "Prometheus: http://localhost:$(FORWARD_PROMETHEUS_PORT)"
	@echo "Loki:       http://localhost:$(FORWARD_LOKI_PORT)/ready"

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

coverage: ## Every suite with line coverage; fails below 100%
	$(PHP) vendor/bin/phpunit --coverage-clover=build/coverage/clover.xml --coverage-html=build/coverage/html --coverage-text --only-summary-for-coverage-text
	$(PHP) tools/check-coverage.php build/coverage/clover.xml 100

infection: ## Mutation testing on Domain + Application (MSI and covered MSI at 100%)
	$(EXEC) vendor/bin/infection --no-progress --show-mutations

##@ Load tests

LOAD_RUN  := $(COMPOSE) --profile load run --rm $(if $(filter debian,$(TARGET)),-e BASE_URL=http://app-debian:8000,)
K6_OUTPUT  = $(if $(shell $(COMPOSE) ps --status running -q prometheus 2>/dev/null),-o experimental-prometheus-rw,)
K6_ENV     = -e RUN_LABEL=$(or $(RUN),local) $(if $(DURATION),-e DURATION=$(DURATION),) $(if $(VUS),-e VUS=$(VUS),) $(if $(LOAD_RATE),-e LOAD_RATE=$(LOAD_RATE),)

.PHONY: load-seed load-reset load-smoke load-load load-capacity load-stress load-concurrency load-pipeline
load-seed: ## Seed the load test users (load-user-NN, load-admin-NN) and ~10k travel requests
	$(PHP) artisan db:seed --class='Infrastructure\Identity\Seeder\LoadTestUsersSeeder' --force
	$(PHP) artisan db:seed --class='Infrastructure\TravelRequest\Seeder\LoadTestTravelRequestsSeeder' --force

load-reset: ## Put the load test data back to the seeded state (drops their requests and notifications, published outbox rows and queued decisions)
	$(COMPOSE) exec -T mysql sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" "$$MYSQL_DATABASE" -e " \
		DELETE n FROM notifications n JOIN users u ON u.id = n.notifiable_id WHERE u.email LIKE \"load-%\"; \
		DELETE t FROM travel_requests t JOIN users u ON u.id = t.requester_id WHERE u.email LIKE \"load-user-%\"; \
		DELETE FROM outbox_messages WHERE published_at IS NOT NULL;" 2>/dev/null'
	@for queue in notification.travel_request notification.travel_request.retry.10s notification.travel_request.retry.1m notification.travel_request.retry.5m; do \
		$(COMPOSE) exec -T rabbitmq rabbitmqctl purge_queue $$queue >/dev/null 2>&1 || true; \
	done
	$(PHP) artisan cache:clear
	@$(MAKE) --no-print-directory load-seed

load-smoke: ## Load test: smoke, 1 VU for 30 s (RUN=<label> names the result file; TARGET=debian hits app-debian)
	@mkdir -p build/load
	$(LOAD_RUN) k6 run $(K6_OUTPUT) $(K6_ENV) /scripts/smoke.js

load-load: ## Load test: design load (~100 req/s) for 5 min against the latency and error targets (LOAD_RATE=<iterations/s>, DURATION=)
	@mkdir -p build/load
	$(LOAD_RUN) k6 run $(K6_OUTPUT) $(K6_ENV) /scripts/load.js

load-capacity: ## Load test: 50 VUs as fast as the service answers, recording throughput and latency (VUS=, DURATION=)
	@mkdir -p build/load
	$(LOAD_RUN) k6 run $(K6_OUTPUT) $(K6_ENV) /scripts/capacity.js

load-stress: ## Load test: ramp 50 -> 400 VUs in 2-min stages and report the ceiling (last stage within the read target)
	@mkdir -p build/load
	$(LOAD_RUN) k6 run $(K6_OUTPUT) $(K6_ENV) /scripts/stress.js

load-concurrency: ## Load test: 20 admins decide each of 50 requests at once; exactly one wins
	@mkdir -p build/load
	$(LOAD_RUN) k6 run $(K6_OUTPUT) $(K6_ENV) /scripts/concurrency.js

load-pipeline: ## Decision-to-notification latency of the load users (p50/p95/p99) and the messages still queued
	@$(COMPOSE) exec -T mysql sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" "$$MYSQL_DATABASE" 2>/dev/null' < tests/Load/pipeline-report.sql
	@$(COMPOSE) exec -T rabbitmq rabbitmqctl list_queues -q name messages | grep notification.travel_request

.PHONY: load-debian-up load-debian-down
load-debian-up: ## Build and start the Debian (glibc) variant of the app, to compare it with Alpine under load
	$(COMPOSE) --profile debian up -d --build --wait app-debian

load-debian-down: ## Stop the Debian variant
	$(COMPOSE) --profile debian rm -sf app-debian

##@ Documentation

.PHONY: docs docs-down
docs: ## Start the interactive C4 diagrams (LikeC4) with live reload; play the dynamic views step by step
	$(COMPOSE) --profile docs up -d likec4
	@echo "Diagrams: http://localhost:$(FORWARD_LIKEC4_PORT)  (dynamic views 5.x have a step-by-step walkthrough)"

docs-down: ## Stop the diagrams server
	$(COMPOSE) --profile docs rm -sf likec4

##@ Notifications page

.PHONY: web
web: ## Print the URL of the live notifications page (APP_ENV=local)
	@echo "Notifications page: http://localhost:$(FORWARD_APP_PORT)/docs/notifications  (sign in as Luffy, then run make demo)"
