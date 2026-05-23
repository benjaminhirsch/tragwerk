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