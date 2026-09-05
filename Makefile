.PHONY: up down test stan deptrac cs check concurrency

up:
	docker compose up -d

down:
	docker compose down

test:
	docker compose exec -T php vendor/bin/phpunit --do-not-fail-on-empty-test-suite

stan:
	docker compose exec -T php vendor/bin/phpstan analyse --configuration=phpstan.dist.neon --no-progress

deptrac:
	docker compose exec -T php vendor/bin/deptrac analyse --config-file=deptrac.yaml --cache-file=var/.deptrac.cache

cs:
	docker compose exec -T php vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php

check: cs stan deptrac test

concurrency:
	docker compose exec -T php vendor/bin/phpunit --testsuite concurrency
