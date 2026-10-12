# Учебное окружение: nginx + php-fpm + PostgreSQL

# Подтягиваем .env как переменные make (нужно, например, для `make psql`).
# Docker Compose сам читает .env для подстановки в compose.yaml — это отдельно, для Makefile.
ifneq (,$(wildcard .env))
include .env
export
endif

.DEFAULT_GOAL := help
.PHONY: help init build up down ps logs clean composer sh psql migrate rollback migrate-status migrate-fresh migration tail-log tail-http config-env config-list config-check key-generate encrypt decrypt

help:
	@echo "make init      — создать .env из .env.example (с UID/GID текущего пользователя)"
	@echo "make build     — собрать образы"
	@echo "make up        — поднять контейнеры (в фоне)"
	@echo "make down      — остановить и удалить контейнеры"
	@echo "make ps        — статус контейнеров"
	@echo "make logs      — логи всех сервисов (follow)"
	@echo "make clean     — down -v: остановить и удалить volume pgdata (данные будут потеряны)"
	@echo "make composer  — composer install внутри php-контейнера"
	@echo "make sh        — shell в php-контейнере"
	@echo "make psql      — консоль psql к postgres"
	@echo "make migrate         — выполнить новые миграции (Eloquent/Illuminate Migrator)"
	@echo "make rollback        — откатить последний batch миграций"
	@echo "make migrate-status  — статус миграций"
	@echo "make migrate-fresh   — откатить все миграции и накатить заново (данные будут потеряны)"
	@echo "make migration name=create_posts_table [create=posts|table=users] — создать файл миграции"
	@echo "make config-env      — текущее окружение (APP_ENV), есть ли APP_CONFIG_KEY"
	@echo "make config-list     — параметры текущего окружения (секреты скрыты)"
	@echo "make config-check    — проверить, что все enc:v1:... значения расшифровываются"
	@echo "make key-generate    — сгенерировать ключ шифрования для APP_CONFIG_KEY"
	@echo "make encrypt         — зашифровать значение (спросит ввод; или value=...)"
	@echo "make decrypt value=enc:v1:... — расшифровать значение"
	@echo "make tail-log        — читать лог приложения (app/storage/logs, все каналы)"
	@echo "make tail-http       — читать только лог HTTP-запросов (канал http)"

# Создаёт .env из примера и подставляет реальные UID/GID текущего пользователя,
# чтобы файлы, которые php-fpm пишет в bind mount (например app/vendor), на Linux-хосте
# принадлежали не root/произвольному системному uid, а текущему пользователю
init:
	@if [ -f .env ]; then \
		echo ".env уже существует, пропускаю"; \
	else \
		cp .env.example .env; \
		CURRENT_UID=$$(id -u); \
		CURRENT_GID=$$(id -g); \
		sed -i.bak "s/^UID=.*/UID=$$CURRENT_UID/" .env; \
		sed -i.bak "s/^GID=.*/GID=$$CURRENT_GID/" .env; \
		rm -f .env.bak; \
		echo ".env создан (UID=$$CURRENT_UID, GID=$$CURRENT_GID)"; \
	fi

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

ps:
	docker compose ps

logs:
	docker compose logs -f

clean:
	docker compose down -v

# Ставим зависимости от имени www-data — тогда vendor/ на хосте получит
# владельца из UID/GID, заданных при `make init`, а не root
composer:
	docker compose exec -u www-data php composer install

sh:
	docker compose exec php sh

psql:
	docker compose exec postgres psql -U $(POSTGRES_USER) -d $(POSTGRES_DB)

# Миграции запускаем от www-data, чтобы созданные файлы принадлежали вам, а не root
migrate:
	docker compose exec -u www-data php php bin/migrate migrate

rollback:
	docker compose exec -u www-data php php bin/migrate rollback

migrate-status:
	docker compose exec -u www-data php php bin/migrate status

migrate-fresh:
	docker compose exec -u www-data php php bin/migrate fresh

# make migration name=create_posts_table create=posts
migration:
	@test -n "$(name)" || { echo "Usage: make migration name=<имя> [create=<таблица> | table=<таблица>]"; exit 1; }
	docker compose exec -u www-data php php bin/migrate make $(name) $(if $(create),--create=$(create)) $(if $(table),--table=$(table))

# Логи пишутся в bind mount ./app, поэтому читать их можно прямо с хоста
tail-log:
	tail -F app/storage/logs/app-*.log

tail-http:
	tail -F app/storage/logs/app-*.log | grep --line-buffered ' http\.'

# --- Статический конфиг и шифрование (app/config/params.php, bin/config) ---
config-env:
	docker compose exec -u www-data php php bin/config env

config-list:
	docker compose exec -u www-data php php bin/config list

config-check:
	docker compose exec -u www-data php php bin/config check

key-generate:
	docker compose exec -u www-data php php bin/config key:generate

# make encrypt            - значение спросят с клавиатуры (не попадёт в историю shell)
# make encrypt value=...  - значение в аргументе
encrypt:
	docker compose exec -u www-data php php bin/config encrypt $(value)

decrypt:
	@test -n "$(value)" || { echo "Usage: make decrypt value=enc:v1:..."; exit 1; }
	docker compose exec -u www-data php php bin/config decrypt $(value)
