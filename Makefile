# ======================================================
# Makefile Bundle
# ======================================================

# --------------------------------------
# Conteneurs Docker
# --------------------------------------
# var/ directories live in Docker volumes: the container must not reuse the
# host's generated files (the dart-sass binary downloaded for macOS cannot run
# on Linux, caches hold host paths).
VAR_VOLUMES=-v gingerminds-core-var:/app/var -v gingerminds-core-app-var:/app/tests/Application/var
PHP=docker run --rm -v $(PWD):/app $(VAR_VOLUMES) -w /app php:8.4-cli
COMPOSER=docker run --rm -v $(PWD):/app -w /app composer:latest
CONSOLE=$(PHP) php tests/Application/bin/console

# --------------------------------------
# Setup
# --------------------------------------
install:
	$(COMPOSER) install

update:
	$(COMPOSER) update

# --------------------------------------
# Test application (tests/Application)
# --------------------------------------
assets:
	$(CONSOLE) importmap:install
	$(CONSOLE) sass:build

serve:
	php -S 127.0.0.1:8000 -t tests/Application/public tests/Application/public/index.php

# --------------------------------------
# Tools / Quality
# --------------------------------------
phpunit: assets
	$(PHP) ./vendor/bin/phpunit

phpstan:
	$(PHP) ./vendor/bin/phpstan analyse --memory-limit=1G

phpcs:
	$(PHP) ./vendor/bin/phpcs

php-cs-fixer:
	$(PHP) ./vendor/bin/php-cs-fixer fix

rector:
	$(PHP) ./vendor/bin/rector

fix-codestyle: rector php-cs-fixer

qa: phpstan phpcs phpunit

# --------------------------------------
# Alias pratique
# --------------------------------------
.PHONY: install update assets serve phpunit phpstan phpcs php-cs-fixer rector fix-codestyle qa
