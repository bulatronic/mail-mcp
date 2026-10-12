# mail-mcp

Небольшой MCP-сервер на PHP 8.4: читает рабочий ящик Mail.ru по IMAP и готовит **черновики** ответов. Отправка писем не поддерживается.

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

Сервис слушает `http://127.0.0.1:8080/mcp`.

### Проверки

```bash
docker compose exec mail-mcp vendor/bin/phpunit
docker compose exec mail-mcp vendor/bin/phpstan analyse
docker compose exec mail-mcp vendor/bin/deptrac analyse --config-file=deptrac.yaml --cache-file=/tmp/mail-mcp-deptrac.cache
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

- Только IMAP APPEND черновика (`\Draft`, `\Seen`) в Drafts; без SMTP и без изменения флагов существующих писем.
- Чтение через EXAMINE / BODY.PEEK (`leaveUnread`).
- Получатель черновика только из Reply-To/From исходного письма.
- Аудит каждого вызова инструмента — JSON line в stdout: `time`, `tool`, `uid`, `status`, `duration_ms`.
- Лимит: **60 вызовов инструментов в минуту** (общий на процесс-группу, файл в `MCP_SESSION_DIR`).
- Ошибки инструментов отдаются как MCP tool errors без секретов и стек-трейсов.
- Архитектурный тест запрещает в `src/` операции delete/expunge/move/copy/setFlag/markSeen, STORE, smtp, Transport, PHPMailer, `mail()`.

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
