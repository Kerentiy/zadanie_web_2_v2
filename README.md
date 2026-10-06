# Учебное окружение: nginx + php-fpm + PostgreSQL + Eloquent + Monolog

Каркас — [local_zoo_for_vyatgu](https://github.com/romicharroyo/local_zoo_for_vyatgu): Docker Compose-стек
(nginx → php-fpm 8.5 → PostgreSQL 18, чистый PHP без фреймворков, образы собираются из `docker/`).
Поверх него добавлено:

| Что | Как |
|---|---|
| **Eloquent ORM** ([docs 12.x](https://laravel.com/docs/12.x/eloquent)) | `illuminate/database` (standalone, `Capsule`); `index.php` и `health.php` больше не используют PDO |
| **Мигратор** | Illuminate Migrator + CLI `app/bin/migrate`; схему БД создают миграции, а не `initdb` |
| **Monolog** ([руководство](https://seldaek.github.io/monolog/doc/01-usage.html)) | логи в `app/storage/logs/`, каждый HTTP-запрос логируется вместе с телом ответа |

## Быстрый старт

```bash
make init && make up && make composer && make migrate
```

- `make init` — создаёт `.env` из `.env.example` с UID/GID текущего пользователя.
- `make up` — собирает и поднимает контейнеры.
- `make composer` — ставит зависимости приложения в `app/vendor/`.
- `make migrate` — выполняет миграции (создаёт таблицы `demo` и `users`, добавляет первую запись в `demo`).

После этого:

- приложение — http://127.0.0.1:8080/ (страница со списком записей `demo` и формой, всё через Eloquent)
- health-check — http://127.0.0.1:8080/health.php
- JSON API — `GET/POST http://127.0.0.1:8080/users`, `GET /users/{id}`
- PostgreSQL — `127.0.0.1:5432` (логин/пароль/база из `.env`), либо `make psql`

Все команды — в `make help`.

## Команды Makefile

| Команда | Что делает |
|---|---|
| `make init / build / up / down / ps / logs / clean` | как в исходном каркасе (`clean` удаляет том с данными PostgreSQL) |
| `make composer` | `composer install` внутри php-контейнера |
| `make sh`, `make psql` | shell в php-контейнере, консоль psql |
| `make migrate` | выполнить новые миграции |
| `make migrate-status` | показать статус миграций |
| `make rollback` | откатить последний batch |
| `make migrate-fresh` | откатить всё и накатить заново (**данные будут потеряны**) |
| `make migration name=create_posts_table create=posts` | создать файл миграции (`create=` — новая таблица, `table=` — изменение существующей) |
| `make tail-log` | читать лог приложения (все каналы) |
| `make tail-http` | читать только лог HTTP-запросов |

## Структура

```
compose.yaml, Makefile, .env.example   оркестрация и команды
docker/                                Dockerfile'ы и конфиги nginx / php-fpm / postgres
app/                                   приложение (bind mount в контейнеры)
├── public/index.php                   главная страница + маршрут /users (Eloquent)
├── public/health.php                  health-check (Eloquent)
├── bootstrap.php                      общий старт: .env → config → Monolog → Eloquent → лог запросов
├── bin/migrate                        CLI мигратора
├── config/                            app.php, database.php, logging.php
├── database/migrations/               миграции (формат как в Laravel)
├── src/Application.php                сборка приложения
├── src/Database/Database.php          подключение Eloquent (Capsule), лог SQL
├── src/Database/MigrationRunner.php   обёртка над Illuminate Migrator
├── src/Http/HttpLogger.php            лог каждого HTTP-запроса и тела ответа
├── src/Http/UserApi.php, Json.php     JSON API поверх модели User
├── src/Logging/LoggerFactory.php      настройка Monolog
├── src/Models/Demo.php, User.php      Eloquent-модели
└── storage/logs/                      app-YYYY-MM-DD.log
```

## Eloquent

Подключение поднимается в `App\Database\Database::boot()` из `app/config/database.php`. В Docker
параметры приходят из `compose.yaml` (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`), драйвер — `pgsql`.
Часовой пояс сессии PostgreSQL выравнивается по PHP (`date.timezone` из `docker/php/php.ini`), чтобы `timestamptz` не «плыл».

```php
use App\Models\Demo;

Demo::create(['note' => 'Привет']);
Demo::query()->orderByDesc('id')->get();
```

Для запуска **без Docker** (PHP ≥ 8.2 + `pdo_sqlite`): `cd app && cp .env.example .env`, в `.env` поставить
`DB_CONNECTION=sqlite`, затем `composer install && php bin/migrate migrate && php -S 127.0.0.1:8000 -t public`.

## Миграции

Файлы лежат в `app/database/migrations/` в формате Laravel (анонимный класс `extends Migration`). Вместо фасада
`Schema::` используется `Capsule::schema()`. Тестовые миграции: `create_users_table`, `create_demo_table`.

> `docker/postgres/initdb/01-init.sql` больше не создаёт таблицу `demo` — схемой управляют миграции. Как и раньше,
> `initdb` выполняется только на пустом томе (`make clean && make up` пересоздаёт БД, затем `make migrate`).

## Логирование (Monolog)

Настроено по [руководству Monolog](https://seldaek.github.io/monolog/doc/01-usage.html):

* **Logger + handler.** Один `Logger` с `RotatingFileHandler` (уровень из `LOG_LEVEL`, ежедневная ротация,
  хранится `LOG_MAX_FILES` файлов) → `app/storage/logs/app-YYYY-MM-DD.log`.
* **Formatter.** `LineFormatter`: `[время] канал.УРОВЕНЬ [request-id]: сообщение {контекст}`.
* **Processors** (`pushProcessor`): `PsrLogMessageProcessor` и `UidProcessor` (`extra.uid` = request-id).
* **Каналы** (`Logger::withName()`): `app` и `http` делят один handler и пишут в один файл;
  фильтровать удобно по каналу: `make tail-http`.
* **Уровни:** SQL — INFO (`LOG_QUERIES=true`), HTTP 2xx/3xx — INFO, 4xx — WARNING, 5xx — ERROR,
  необработанное исключение — CRITICAL.

### Лог HTTP-запросов (канал `http`)

`app/bootstrap.php` подключается первой строкой каждой точки входа (`index.php`, `health.php`) и включает
`HttpLogger`: он буферизует вывод и пишет запись в `shutdown`-функции — поэтому лог создаётся даже при `exit()`
или фатальной ошибке. В запись попадают метод, URI, IP, заголовки и тело запроса, статус, заголовки и
**тело ответа**, длительность. Каждый ответ содержит заголовок `X-Request-Id`, этот же id есть во всех записях
запроса (SQL, события приложения, `http`).

* Значения `Authorization`, `Cookie` и т. п. и JSON-поля `password`, `token`… заменяются на `***`
  (списки в `app/config/logging.php`).
* Тела длиннее `LOG_BODY_MAX_BYTES` (10 КБ) обрезаются, бинарные данные не пишутся.

Логи пишутся в bind mount `./app`, поэтому читать их можно прямо с хоста: `make tail-http`.

## JSON API

```bash
curl -XPOST localhost:8080/users -H 'Content-Type: application/json' \
  -d '{"name":"Ada","email":"ada@example.com"}'
curl localhost:8080/users
curl localhost:8080/users/1
```

> Windows: консоль может отправить кириллицу в `curl -d` не в UTF-8 — сервер ответит `400 Invalid JSON body`.
> Используйте `\uXXXX`-escape, JSON из файла (`--data-binary @body.json`) или латиницу.

## Когда пересобирать, а когда достаточно F5 или restart

- **Правки в `app/`** (PHP-код, миграции) — видны сразу, без пересборки (`./app` смонтирован volume'ом,
  opcache проверяет файлы на каждый запрос). Новую миграцию примените через `make migrate`.
- **Правки в конфигах** (`docker/nginx/conf.d/*.conf`, `php.ini`, `www.conf`, `postgresql.conf`) — нужен
  `docker compose restart <сервис>`.
- **Правки в `Dockerfile` или `app/composer.json`** — `make build`, затем `make up` (и `make composer`,
  если менялся `composer.json`).

## Linux / macOS / Windows (WSL2)

`make init` подставляет в `.env` UID/GID текущего пользователя, а Dockerfile php пересоздаёт `www-data` с этими
же ID — файлы, которые пишет php-fpm (`app/vendor`, `app/storage/logs`), на хосте принадлежат вам, а не root.

- **Linux / macOS (Docker Desktop)** — работает «из коробки».
- **Windows + WSL2** — запускайте `make`/`docker compose` из терминала WSL2 (не из PowerShell/cmd/Git Bash) и держите
  проект **внутри файловой системы WSL2** (`~/projects/...`), а не на `/mnt/c/...`. В Docker Desktop должна быть
  включена WSL-интеграция.
- **Переносы строк** — `.gitattributes` форсирует LF для всех файлов репозитория.

## Лицензия

MIT
