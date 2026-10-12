# mail-mcp

Небольшой MCP-сервер на PHP 8.4: читает рабочий ящик Mail.ru по IMAP и готовит **черновики** ответов. Отправка писем не поддерживается.

## Сессии

Выбран **`FileSessionStore`** в каталоге `MCP_SESSION_DIR`.

SDK умеет обслуживать modern-era (SEP-2575) запросы без сессии внутри `StreamableHttpTransport`, но клиенты handshake-эры (в том числе MCP Inspector) открывают `initialize`-сессию и шлют последующие запросы с `Mcp-Session-Id`. PHP обрабатывает HTTP-запросы в отдельных процессах, поэтому `InMemorySessionStore` не подходит. Отдельный `StatelessHttpTransport` / `buildStateless()` — только modern-era и ломает Inspector.

## Быстрый старт (dev)

```bash
cp .env.example .env
# заполните MAIL_USER и MAIL_APP_PASSWORD

docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile tools run --rm composer install
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec mail-mcp vendor/bin/phpunit
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec mail-mcp vendor/bin/phpstan analyse
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec mail-mcp vendor/bin/deptrac analyse --config-file=deptrac.yaml
```

Слои контролирует Deptrac (`deptrac.yaml`): `Mcp → Application → Ports ← Infrastructure`, Domain ни от кого не зависит.

MCP Inspector:

```bash
npx @modelcontextprotocol/inspector
```

Подключение к `http://127.0.0.1:8080/mcp` — должен быть виден инструмент `ping`.

## Безопасность

- Только IMAP APPEND черновика (`\Draft`) в Drafts; без SMTP и без изменения чужих флагов.
- Чтение через EXAMINE / BODY.PEEK.
- `MAIL_USER` / `MAIL_APP_PASSWORD` только из окружения; пароль, темы и тела писем не логируются.
