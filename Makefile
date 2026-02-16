.PHONY: up-d down exec composer-i composer-u generate commit-a

DOCKER_COMPOSE = docker-compose
PHP_BASH = docker exec -it php_recipe /bin/bash
DOCKER_EXEC = $(PHP_BASH) -c

# Docker Compose Commands
up-d:
	$(DOCKER_COMPOSE) up -d


down:
	$(DOCKER_COMPOSE) down --remove-orphans

exec:
	$(DOCKER_EXEC) "echo -e '\033[32m'; /bin/bash"

generate:
	$(DOCKER_EXEC) "php bin/generate.php"

composer:
	$(DOCKER_EXEC) "composer $(CMD)"

composer-install: CMD = install
composer-install: composer

composer-update: CMD = update
composer-update: composer

composer-i: composer-install
composer-u: composer-update

commit-a:
	@printf "\033[33mПідтвердити push з amend? (y/N): \033[0m"; \
	read CONF && [ "$$CONF" = "y" ] || exit 1; \
	git add .; \
	git commit --no-edit --amend; \
	git push --force; \
	printf "\033[33mTag (enter щоб пропустити): \033[0m"; \
	read TAG; \
	[ -z "$$TAG" ] && exit 0; \
	git tag -d $$TAG 2>/dev/null || true; \
	git push origin :refs/tags/$$TAG; \
	git tag $$TAG; \
	git push origin $$TAG

# Application Specific Commands
console: setup
	$(DOCKER_EXEC) "php bin/console $(EXEC)"

# Це скидає будь-які аргументи передані до 'run', роблячи їх не-цілями
%:
	@:

