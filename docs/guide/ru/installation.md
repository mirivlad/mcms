# Установка mCMS

[К оглавлению](README.md) · [English](../en/installation.md)

## Вариант A: Docker Compose / Portainer

**Необходимы:** Docker + Compose либо Portainer с Docker Standalone; домен, HTTPS reverse proxy и место на диске. MariaDB уже входит в Stack. Репозиторий: [mirivlad/mcms](https://github.com/mirivlad/mcms). Порт по умолчанию: 127.0.0.1:8188.

### Portainer

**Рекомендуемый режим для автоматических обновлений ядра:** [пошаговая настройка релизного Stack и Portainer API](portainer-auto-updates.md). При обычном `portainer-stack.yml` Core обновляется только через ручной Git Re-pull/Redeploy.

1. **Stacks → Add stack → Repository**. Укажите URL репозитория и ветку `main`. Для Git Stack: **Compose path** `portainer-stack.yml`. Если репозиторий приватный, настройте Git-доступ в Portainer.
2. В переменных Stack задайте **разные сильные пароли** `MCMS_DB_PASSWORD` и `MCMS_DB_ROOT_PASSWORD`. Дополнительно: `MCMS_DB_NAME` и `MCMS_DB_USER` (по умолчанию `mcms`), `MCMS_HTTP_PORT` (8188), `MCMS_BIND_ADDRESS` (127.0.0.1), `MCMS_MAIL_DOMAIN`, `MCMS_MAIL_FROM`.
3. Разверните Stack с **пересборкой** образов из Git. Публиковать 8188 наружу не нужно: направьте внешний Nginx/Caddy с HTTPS на `http://127.0.0.1:8188`, передавая `Host`, `X-Forwarded-For` и `X-Forwarded-Proto`. Убедитесь, что `MCMS_TRUSTED_PROXIES` соответствует вашему proxy.
4. Откройте домен по HTTPS. На новом сайте появится `/install`: задайте название сайта, email, логин и пароль первого суперпользователя. Регистрацию пользователей можно разрешить или оставить выключенной.
5. Проверьте вход, `/admin`, медиа, страницы, каталог. В «Модулях» включите требуемые встроенные компоненты.

При запуске контейнер `app` выполняет `php spark mcms:update`: мигрирует Core, Settings, Shield и только включённые модули. Команда **не очищает** БД. Не включайте `MCMS_DEMO_SEED=1` на рабочем сайте.

### Docker Compose без Portainer

```bash
git clone https://github.com/mirivlad/mcms.git
cd mcms
cp .env.example .env
# Задайте уникальные MCMS_DB_PASSWORD и MCMS_DB_ROOT_PASSWORD в .env
docker compose up -d --build
docker compose ps
docker compose logs --tail=100 app web db
```

Настройка reverse proxy и мастер `/install` — те же. Образы можно получать из GHCR через `portainer-release-stack.yml`, если вы сознательно переходите на режим опубликованных release images; **не создавайте второй Stack на тех же томах**.

### Данные и тома

| Том | Что хранит |
|---|---|
| `db_data` | База MariaDB |
| `modules_data` | Установленные модули и их файлы, медиа |
| `themes_data` | Пользовательские темы и ресурсы |
| `backup_data` | ZIP резервных копий и состояние восстановления |
| `mail_queue`, `dkim_data` | Почтовая очередь и ключи Postfix |

Названия томов зависят от имени Compose-проекта/Stack. При обновлении **сохраняйте имя Stack** и не применяйте `docker compose down -v` / удаление томов.

## Вариант B: standalone / shared hosting

**Требования:** PHP 8.2+ с mysqli, intl, mbstring, gd, curl, zip, fileinfo и расширениями CodeIgniter; Apache 2.4 c `mod_rewrite` и разрешённым `.htaccess`; MySQL/MariaDB с правами CREATE/ALTER; запись в `writable`; возможность держать файлы **выше DocumentRoot**. Достаточный лимит загрузки/времени PHP зависит от размеров сайта. Для больших сайтов низкие лимиты shared-хостинга могут сделать резервное копирование и восстановление недоступными.

1. Получите **`mcms-X.Y.Z-shared-hosting.zip`** со страницы официального [GitHub Release](https://github.com/mirivlad/mcms/releases). **Не** используйте ZIP «Source code» с GitHub: в нём нет PHP-зависимостей и собранных CSS/JS.
2. В панели хостинга создайте **пустую БД** и пользователя с доступом к ней. Включите HTTPS. Желательно установить PHP 8.3/8.4 с требуемыми расширениями.
3. Распакуйте релиз в каталог аккаунта **над веб-корнем**. Содержимое папки `public_html` из релиза должно совпасть с DocumentRoot домена. Например:

   ```text
   /home/account/site/
       .env                 <- приватно: пароль БД
       app/ src/ vendor/ modules/ themes/ catalog/
       shared-core-rescue.php
       writable/            <- приватно: файлы, бэкапы, логи
       public_html/         <- ТОЛЬКО этот каталог доступен по HTTP
           index.php .htaccess assets/
   ```
4. Откройте домен по **HTTPS**. Мастер `/install` проверяет PHP, расположение каталогов и запись, запросит MySQL-host, порт (обычно 3306), имя БД, пользователя и пароль. SMTP можно указать необязательно.
5. Нажмите подключение. CMS создаст свои таблицы и откроет мастер первого администратора. Заполните его и проверьте вход. Устанавливать Composer, запускать `spark` или импортировать SQL вручную **не нужно**.

**Категорически не размещайте** `vendor`, `src`, `app`, `writable`, `.env` или бэкапы внутри доступного всем `public_html`. На тарифах, где невозможно задать DocumentRoot только на публичную директорию, используйте другой тариф/хостинг: отключать защиту установщика нельзя. Файл `.env` должен быть доступен только PHP-пользователю.

### После установки

В `/admin` проверьте смену пароля, темы, список модулей, отправку почты через SMTP и создание первого ZIP-бэкапа с его **скачиванием на ваш компьютер**. Планируйте резервирование до первого наполнения сайта, а не после аварии.
