.PHONY: help
help:
	@echo 'Usage:'
	@sed -n 's/^##//p' ${MAKEFILE_LIST} | column -t -s ':' | sed -e 's/^/ /'

## test-unit: Run unit tests
.PHONY: test-unit
test-unit:
	docker compose run --rm --env XDEBUG_MODE=off app composer run test -- --testsuite unit

## test-integration: Run integration tests (requires running db container)
.PHONY: test-integration
test-integration:
	docker compose run --rm --env XDEBUG_MODE=off app composer run test -- --testsuite integration

## test: Run all tests in parallel
.PHONY: test
test:
	docker compose run --rm --env XDEBUG_MODE=off app composer run test

PARATEST_COVERAGE := php -d pcov.enabled=1 vendor/bin/paratest --processes auto --passthru-php="-d pcov.enabled=1" --colors=always --coverage-html /app/public/coverage

## coverage: Run all tests with HTML coverage report → app/coverage/index.html
.PHONY: coverage
coverage:
	docker compose run --rm --env XDEBUG_MODE=off app $(PARATEST_COVERAGE)

## coverage-unit: Run unit tests with HTML coverage report
.PHONY: coverage-unit
coverage-unit:
	docker compose run --rm --env XDEBUG_MODE=off app $(PARATEST_COVERAGE) --testsuite unit

## coverage-integration: Run integration tests with HTML coverage report
.PHONY: coverage-integration
coverage-integration:
	docker compose run --rm --env XDEBUG_MODE=off app $(PARATEST_COVERAGE) --testsuite integration

## test-wrapper: Run sshd git-auth-wrapper bats tests (dockerized bats)
.PHONY: test-wrapper
test-wrapper:
	docker run --rm -v $(PWD):/code -w /code bats/bats:latest docker/sshd/test/

## check: Run all checks
.PHONY: check
check:
	docker compose run --rm --env XDEBUG_MODE=off app composer run check && docker compose run --rm --env XDEBUG_MODE=off app composer run test

## cscheck: Run code style checks
.PHONY: cscheck
cscheck:
	docker compose run --rm --env XDEBUG_MODE=off app composer run cs-check

## csfix: Fix all fixable code style errors
.PHONY: csfix
csfix:
	docker compose run --rm --env XDEBUG_MODE=off app composer run cs-fix

## lint: PHP Linter
.PHONY: lint
lint:
	docker compose run --rm --env XDEBUG_MODE=off app composer run phplint

## static-analysis: Static analysis
.PHONY: static-analysis
static-analysis:
	docker compose run --rm --env XDEBUG_MODE=off app composer run static-analysis

## db/migrations/new: Create a blank migration
.PHONY: db/migrations/new
db/migrations/new:
	docker compose run --rm --env XDEBUG_MODE=off app bin/cli migrations:generate

## db/migrations/rollback: Rollback the last migration
.PHONY: db/migrations/rollback
db/migrations/rollback:
	docker compose run --rm --env XDEBUG_MODE=off app bin/cli migrations:migrate prev

## db/migrations/status: Show current migration status
.PHONY: db/migrations/status
db/migrations/status:
	docker compose run --rm --env XDEBUG_MODE=off app bin/cli migrations:status

## db/migrations/migrate: Apply pending migrations
.PHONY: db/migrations/migrate
db/migrations/migrate:
	docker compose run --rm --env XDEBUG_MODE=off app bin/cli migrations:migrate

PROD_COMPOSE := docker compose -f docker-compose.prod.yaml

## prod/build: Build the production app/docs/sshd images on this host
.PHONY: prod/build
prod/build:
	$(PROD_COMPOSE) build

## prod/up: Build (if needed) and start the full production stack detached
.PHONY: prod/up
prod/up:
	$(PROD_COMPOSE) up -d --build

PROD_LOCAL := docker compose --env-file .env.local -f docker-compose.prod.yaml -f docker-compose.prod.local.yaml

# Working copy of the local env (gitignored); seeded from the committed template.
.env.local:
	cp .env.local.dist .env.local

## prod/local: Run the full prod stack locally over HTTP (no TLS/ACME), hosts *.localhost
.PHONY: prod/local
prod/local: .env.local
	$(PROD_LOCAL) up -d --build
	@echo 'app : curl -H "Host: tragwerk.localhost" http://localhost/login'
	@echo 'docs: curl -H "Host: docs.localhost"     http://localhost/'
	@echo 'traefik dashboard: http://localhost:8080'

## prod/local/down: Stop the local prod stack and remove its volumes
.PHONY: prod/local/down
prod/local/down:
	$(PROD_LOCAL) down -v

## prod/down: Stop and remove the production stack
.PHONY: prod/down
prod/down:
	$(PROD_COMPOSE) down

## prod/logs: Tail logs across all production services
.PHONY: prod/logs
prod/logs:
	$(PROD_COMPOSE) logs -f

DOCS_COMPOSE := docker compose -f docker-compose.docs.yaml

## docs/dev: Serve docs with hot reload at http://localhost:5173
.PHONY: docs/dev
docs/dev:
	$(DOCS_COMPOSE) up

## docs/stop: Stop the docs dev server and remove its container
.PHONY: docs/stop
docs/stop:
	$(DOCS_COMPOSE) down

## docs/build: Build static docs site → docs/.vitepress/dist
.PHONY: docs/build
docs/build:
	$(DOCS_COMPOSE) run --rm docs sh -c "npm install && npm run docs:build"

## docs/install: Install/refresh docs npm dependencies
.PHONY: docs/install
docs/install:
	$(DOCS_COMPOSE) run --rm docs npm install

## docs/clean: Remove docs node_modules and build output
.PHONY: docs/clean
docs/clean:
	$(DOCS_COMPOSE) run --rm docs sh -c "rm -rf node_modules .vitepress/dist .vitepress/cache .npm-cache"