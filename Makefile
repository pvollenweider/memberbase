.PHONY: up down logs shell db import open

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
