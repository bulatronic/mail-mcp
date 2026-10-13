# mail-mcp

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?style=flat&logo=php)](https://www.php.net/)
[![MCP](https://img.shields.io/badge/MCP-Streamable%20HTTP-0A7EA4?style=flat)](https://modelcontextprotocol.io/)
[![FrankenPHP](https://img.shields.io/badge/FrankenPHP-Ready-000000?style=flat)](https://frankenphp.dev/)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat&logo=docker)](https://www.docker.com/)
[![Symfony Mime](https://img.shields.io/badge/Symfony%20Mime-7%2F8-000000?style=flat&logo=symfony)](https://symfony.com/doc/current/components/mime.html)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-11-3C9A40?style=flat)](https://phpunit.de/)

Небольшой MCP-сервер на PHP 8.5: читает рабочий ящик Mail.ru по IMAP и готовит **черновики** ответов.

**Отправка писем не поддерживается — умышленно.** ИИ-агент не должен иметь возможности случайно навредить ящику: в коде и портах нет SMTP/`mail()`, удаления, перемещения, копирования, пересылки, EXPUNGE и смены флагов существующих писем. Единственная запись — IMAP APPEND черновика в Drafts. Отправить ответ можно только вручную из почтового клиента.

## Стек

| Технология                                 | Роль                                         |
|--------------------------------------------|----------------------------------------------|
| PHP 8.5 + FrankenPHP                       | Runtime и HTTP (Streamable MCP)              |
| mcp/sdk                                    | MCP-сервер и инструменты                     |
| directorytree/imapengine                   | Pure-PHP IMAP (EXAMINE / BODY.PEEK)          |
| symfony/mime                               | Сборка MIME черновика (без Mailer/Transport) |
| Docker Compose                             | Запуск и изоляция                            |
| PHPUnit / PHPStan / Deptrac / PHP CS Fixer | Тесты, статический анализ, слои, стиль       |

## Что умеет

| Инструмент          | Назначение                                                    |
|---------------------|---------------------------------------------------------------|
| `get_mailbox_state` | Checkpoint INBOX: `uidvalidity`, `last_uid`, `total_messages` |
| `list_new_messages` | Новые письма после `since_uid` (только заголовки)             |
| `read_message`      | Текст одного письма + метаданные вложений                     |
| `find_reply`        | Есть ли ответ в Sent по `message_id`                          |
| `save_draft`        | Черновик ответа в Drafts (`reply_to_uid` + `body`)            |

Новый инструмент без явного решения ломает snapshot-тест каталога.

## Настройка ящика (IMAP)

1. В Mail.ru включите IMAP: «Почта» → «Все настройки» → «Почтовые программы».
2. Создайте **пароль приложения** (не обычный пароль входа).
3. Параметры сервера по умолчанию:
   - хост: `imap.mail.ru`
   - порт: `993` (SSL)
4. Папки Sent/Drafts определяются по SPECIAL-USE (`\Sent`, `\Drafts`). Если сервер не отдаёт SPECIAL-USE — задайте `MAIL_SENT_FOLDER` / `MAIL_DRAFTS_FOLDER` (имена как в ящике, часто «Отправленные» / «Черновики»).

## Переменные окружения

Скопируйте пример и заполните секреты:

```bash
cp .env.example .env
```

| Переменная           | Обязательно | Описание                                                    |
|----------------------|-------------|-------------------------------------------------------------|
| `MAIL_USER`          | да          | Логин ящика (email)                                         |
| `MAIL_APP_PASSWORD`  | да          | Пароль приложения IMAP                                      |
| `MAIL_FROM_NAME`     | нет         | Отображаемое имя в From черновика                           |
| `MAIL_IMAP_HOST`     | нет         | По умолчанию `imap.mail.ru`                                 |
| `MAIL_IMAP_PORT`     | нет         | По умолчанию `993`                                          |
| `MAIL_SENT_FOLDER`   | нет         | Fallback для Sent                                           |
| `MAIL_DRAFTS_FOLDER` | нет         | Fallback для Drafts                                         |
| `MCP_SESSION_DIR`    | нет         | Каталог сессий MCP и файла rate-limit (`/var/mcp-sessions`) |
| `TZ`                 | нет         | Часовой пояс контейнера                                     |

Пароль, темы и тела писем в логи не пишутся.

## Запуск

### Dev (bind-mount исходников)

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile tools run --rm composer install
```

Сервис слушает `http://127.0.0.1:8080/mcp` (контейнер `mail-mcp`, образ `mail-mcp:local`).

### Остановка

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml down
```

### Проверки

```bash
docker compose exec mail-mcp vendor/bin/phpunit
docker compose exec mail-mcp vendor/bin/phpstan analyse
docker compose exec mail-mcp vendor/bin/deptrac analyse --config-file=deptrac.yaml --cache-file=/tmp/mail-mcp-deptrac.cache
docker compose exec mail-mcp vendor/bin/php-cs-fixer check --diff --config=.php-cs-fixer.dist.php
```

Исправить стиль (нужны права хост-пользователя на bind-mount):

```bash
docker compose exec -u "$(id -u):$(id -g)" mail-mcp vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
```

Альтернатива через Composer-скрипты `cs-check` / `cs-fix` (profile `tools`):

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile tools run --rm composer cs-check
docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile tools run --rm composer cs-fix
```

Слои: `Mcp → Application → Ports ← Infrastructure`, Domain ни от кого не зависит (Deptrac).

## Проверка через MCP Inspector

```bash
npx @modelcontextprotocol/inspector
```

Подключение:

- Transport: **Streamable HTTP**
- URL: `http://127.0.0.1:8080/mcp`

В списке должны быть ровно пять инструментов из таблицы выше. `tools/call` для `get_mailbox_state` должен вернуть checkpoint INBOX.

## Подключение в picoclaw

В сети Docker Compose сервис доступен по имени хоста `mail-mcp`:

```text
http://mail-mcp:8080/mcp
```

Укажите этот URL как Streamable HTTP endpoint MCP-сервера mail-mcp. С хоста машины используйте `http://127.0.0.1:8080/mcp`.

## Безопасность и эксплуатация

Ограничения ниже — продуктовое решение, а не недоделка: поверхность атаки агента намеренно узкая.

- **Нет отправки:** SMTP, Symfony Mailer/Transport, PHPMailer и `mail()` отсутствуют в зависимостях и коде.
- **Нет разрушительных IMAP-операций:** удаление, перемещение, копирование, пересылка, EXPUNGE, `STORE`/смена флагов существующих писем — запрещены портами и архитектурным тестом.
- **Единственная запись:** IMAP APPEND черновика (`\Draft`, `\Seen`) только в Drafts.
- **Чтение только read-only:** EXAMINE / BODY.PEEK (`leaveUnread`), письма не помечаются прочитанными.
- Получатель черновика только из Reply-To/From исходного письма (не из аргументов агента).
- Аудит каждого вызова инструмента — JSON line в stdout: `time`, `tool`, `uid`, `status`, `duration_ms` (+ `error_class` при ошибке); без паролей, тем и тел писем.
- Лимит: **60 вызовов инструментов в минуту** (общий на процесс-группу, файл в `MCP_SESSION_DIR`).
- Ошибки инструментов отдаются как MCP tool errors без секретов и стек-трейсов.

## IMAP-библиотека

Выбран **directorytree/imapengine**:

| Библиотека               | EXAMINE                                | BODY.PEEK          | Вложения без скачивания         |
|--------------------------|----------------------------------------|--------------------|---------------------------------|
| directorytree/imapengine | да (`examine`, адаптер SELECT→EXAMINE) | да (`leaveUnread`) | да (BODYSTRUCTURE)              |
| webklex/php-imap         | examine есть, openFolder делает SELECT | FT_PEEK            | частично                        |
| dg/imap                  | BODY.PEEK[]                            | да                 | нет — тело целиком с вложениями |

Каждый вызов: одно короткое соединение, timeout 15 с.

## Сессии

Используется **`FileSessionStore`** в `MCP_SESSION_DIR`.

SDK умеет обслуживать modern-era (SEP-2575) запросы без сессии, но клиенты handshake-эры (в т.ч. MCP Inspector) шлют `Mcp-Session-Id`. PHP обрабатывает запросы в отдельных процессах, поэтому `InMemorySessionStore` не подходит.
