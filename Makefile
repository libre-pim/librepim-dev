# Makefile for Akeneo PIM without Docker
# Usage:
#   make pim-prod   → full production install
#   make pim-dev    → full dev install
#   make cache      → clear & warm cache
#   make database   → reset DB
#   make assets     → install assets

.PHONY: help
help:
	@echo "Available targets:"
	@echo "  dependencies     Install PHP & JS dependencies"
	@echo "  assets           Clean and install assets"
	@echo "  css              Build CSS"
	@echo "  javascript-dev   Build JS (dev mode)"
	@echo "  javascript-prod  Build JS (prod mode)"
	@echo "  database         Reset database and load fixtures"
	@echo "  cache            Clear and warmup cache"
	@echo "  pim-dev          Full dev installation with demo catalog"
	@echo "  pim-prod         Full prod installation"
	@echo "  admin            Create admin user"
	@echo "  workers          Start message consumers"

##
## Dependencies
##
.PHONY: dependencies
dependencies:
	composer install --optimize-autoloader --prefer-dist
	yarn install --frozen-lockfile

##
## Assets
##
.PHONY: assets
assets:
	rm -rf public/bundles public/js public/css
	php bin/console pim:installer:assets --env=prod --symlink --clean

.PHONY: css
css:
	yarn run less

.PHONY: javascript-dev
javascript-dev:
	yarn run update-extensions
	yarn run packages:build
	yarn run webpack-dev

.PHONY: javascript-prod
javascript-prod:
	yarn run update-extensions
	yarn run packages:build
	yarn run webpack

##
## Cache
##
.PHONY: cache
cache:
	rm -rf var/cache/*
	php bin/console cache:warmup --env=prod

##
## Database
##
.PHONY: database
database:
	php bin/console doctrine:database:drop --force --if-exists --env=prod
	php bin/console doctrine:database:create --if-not-exists --env=prod
	php bin/console pim:installer:db --env=prod

##
## Full installations
##
.PHONY: pim-dev
pim-dev: dependencies cache assets css javascript-dev
	php bin/console doctrine:database:drop --force --if-exists --env=dev
	php bin/console doctrine:database:create --if-not-exists --env=dev
	php bin/console pim:installer:db --catalog src/Akeneo/Platform/Installer/back/src/Infrastructure/Symfony/Resources/fixtures/icecat_demo_dev --env=dev

.PHONY: pim-prod
pim-prod: dependencies cache assets css javascript-prod
	php bin/console doctrine:database:drop --force --if-exists --env=prod
	php bin/console doctrine:database:create --if-not-exists --env=prod
	php bin/console pim:installer:db --env=prod
	make admin

##
## Admin & Workers
##
.PHONY: admin
admin:
	php bin/console pim:user:create admin admin admin@example.com Super Admin en_US --admin -n --env=prod || true

.PHONY: workers
workers:
	php bin/console messenger:consume ui_job import_export_job data_maintenance_job --env=prod
