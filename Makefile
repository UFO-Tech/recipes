.PHONY: up-d down exec composer composer-install composer-update composer-i composer-u generate check test commit-a

DOCKER_COMPOSE = docker-compose
CONTAINER = php_recipe
# -t тільки в терміналі, щоб make check/test працювали і в скриптах
TTY := $(shell [ -t 0 ] && echo -t)
# GITHUB_TOKEN (якщо заданий) прокидається в контейнер для fallback на GitHub API
DOCKER_EXEC = docker exec -i $(TTY) -e GITHUB_TOKEN $(CONTAINER) /bin/bash -c

# Docker Compose Commands
up-d:
	$(DOCKER_COMPOSE) up -d


down:
	$(DOCKER_COMPOSE) down --remove-orphans

exec:
	$(DOCKER_EXEC) "echo -e '\033[32m'; /bin/bash"

# Перегенерувати index.json і recipes/
generate:
	$(DOCKER_EXEC) "php bin/generate.php"

# Нічого не пише; exit 1, якщо index.json/recipes/ застаріли
check:
	$(DOCKER_EXEC) "php bin/generate.php --check"

test:
	$(DOCKER_EXEC) "vendor/bin/phpunit"

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

# Це скидає будь-які аргументи передані до 'run', роблячи їх не-цілями
%:
	@:
