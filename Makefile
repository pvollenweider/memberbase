.PHONY: up down logs shell db import backup restore open test test-ui test-reset-db test-unit migrate migrate-status

open:
	open http://localhost:8080
	open http://localhost:8082

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f php

shell:
	docker compose exec php bash

db:
	docker compose exec mariadb mariadb -umembers -pmembers members

## import DUMP=path/to/dump.sql
import:
	@test -n "$(DUMP)" || (echo "Usage: make import DUMP=path/to/dump.sql" && exit 1)
	docker compose exec -T mariadb mariadb -umembers -pmembers members < $(DUMP)

## backup [FILE=dump.sql] — dump the DB (default: timestamped file)
backup:
	docker compose exec -T mariadb mariadb-dump -umembers -pmembers --single-transaction --routines --triggers members > $(or $(FILE),backup_$(shell date +%Y%m%d_%H%M%S).sql)
	@echo "Backup written."

## restore FILE=dump.sql — restore the DB from a dump (destructive)
restore:
	@test -n "$(FILE)" || (echo "Usage: make restore FILE=dump.sql" && exit 1)
	docker compose exec -T mariadb mariadb -umembers -pmembers members < $(FILE)
	@echo "Restored from $(FILE)."

migrate:
	docker compose exec php php tools/migrate.php

migrate-status:
	docker compose exec php php tools/migrate.php --status

test:
	npx playwright test

test-ui:
	npx playwright test --ui

test-reset-db:
	bash tests/fixtures/reset-db.sh

## PHP unit tests (pure logic) — runs in throwaway containers (no local PHP/Composer needed)
## Mounts the repo root (the running php container only mounts html/), installs
## PHPUnit into vendor/ (gitignored), and runs the suite on PHP 8.2 (prod parity).
test-unit:
	docker run --rm -v "$(CURDIR)":/app -w /app composer:2 install --no-interaction --no-progress
	docker run --rm -v "$(CURDIR)":/app -w /app php:8.2-cli vendor/bin/phpunit
