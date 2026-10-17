# Веб-серверы для standalone mCMS: Apache, Nginx, Caddy и LiteSpeed

[К оглавлению](README.md) · [English](../en/webservers.md) · [Установка](installation.md)

mCMS основана на CodeIgniter 4, но **не требует Apache или mod_rewrite**. Подходит любой веб-сервер, который корректно передаёт маршруты в PHP-front controller и не раскрывает приватные файлы. Примеры ниже предназначены для самостоятельной установки или тарифов хостинга с доступом к настройкам веб-сервера. Все пути в примерах **условные**: замените их на расположение вашей установки, а также подставьте свои домен и сокет PHP-FPM. Абсолютный путь **не задан в коде mCMS**.

## Общие требования ко всем серверам

- **DocumentRoot** должен указывать **на каталог `public_html` вашей установленной mCMS**. Его абсолютный путь может быть любым: `/var/www/domain.ru/public_html`, `/home/user/sites/domain.ru/public_html` или `/srv/mcms/public_html`. Главное — `app`, `src`, `vendor`, `writable`, `.env`, `manual` и другие приватные файлы должны находиться **вне DocumentRoot** и не быть доступны по HTTP.
- Существующие файлы (CSS/JS/шрифты) отдаёт веб-сервер; все остальные запросы проходят через `public_html/index.php`, включая `/account`, `/admin`, `/install` и **`/assets/uploads/media/...`**. Медиа хранятся в приватном `writable/modules-data` и выдаются контроллером PHP. Нельзя бездумно объявлять весь `/assets/` «только статикой»: изображения Media перестанут открываться.
- Обрабатывается только нужный front controller. Не допускайте исполнения произвольных `*.php` в каталоге загрузок. Передавайте PHP корректные `SCRIPT_FILENAME`, `DOCUMENT_ROOT`, `QUERY_STRING` и информацию об исходном URL. Для HTTPS за прокси корректно настройте список доверенных прокси в mCMS, не доверяйте произвольному `X-Forwarded-Proto` из Интернета.
- PHP 8.2+ и требуемые расширения, MySQL/MariaDB, запись PHP-пользователя в приватный `writable` и корневой каталог при первоначальной установке, HTTPS до ввода учётных данных. Для длительных операций учитывайте лимиты FPM/хостинга.

Пример дерева файлов для установки в `/var/www/domain.ru/` (**это пример, не обязательный путь**):

```text
/var/www/domain.ru/
  .env                 # НЕ в DocumentRoot
  app/ src/ vendor/ modules/ themes/ manual/
  writable/            # приватные данные Media, бэкапы, логи
  public_html/         # DocumentRoot, включает index.php и assets/
```

## 1. Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name domain.ru;
    root /var/www/domain.ru/public_html;
    index index.php;
    autoindex off;

    # Пример HTTP за локальным HTTPS reverse proxy.
    # Для прямого внешнего доступа настройте TLS (listen 443 ssl).
    location / {
        try_files $uri /index.php?$query_string;
    }

    # Media хранится ВНЕ public_html: запрос должен попадать в PHP,
    # а не завершаться статическим 404 внутри общего /assets/.
    location ^~ /assets/uploads/media/ {
        try_files $uri /index.php?$query_string;
    }
    location ^~ /assets/ {
        try_files $uri =404;
        expires 7d;
    }

    # Запускаем исключительно штатный front controller.
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ \.php(?:/|$) { return 404; }
    location ~ /\. { return 404; }
}
```

Установите актуальный сокет или адрес FPM-пула (например, `127.0.0.1:9000`). Такой способ не требует `.htaccess` и `mod_rewrite`. Проверьте `nginx -t` и перезагрузите конфигурацию. **Не добавляйте** `location ^~ /assets/ { try_files $uri =404; }` без более специфического исключения для `/assets/uploads/media/`.

## 2. Caddy + PHP-FPM

```caddyfile
domain.ru {
    root * /var/www/domain.ru/public_html
    encode zstd gzip

    # php_fastcgi уже реализует fallback в index.php для несуществующих URL.
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

Caddy сам управляет HTTPS для публичного домена, если DNS/порты настроены. Не добавляйте отдельный `handle /assets/*` только с `file_server`: он обойдёт PHP-маршрут приватных Media. Проверьте `caddy validate --config /etc/caddy/Caddyfile` и откройте `/assets/uploads/media/...` с настоящим изображением.

## 3. Apache (mod_php или PHP-FPM)

**`mod_rewrite` не обязателен.** Варианты:

- С `mod_rewrite`: используйте поставляемый `public_html/.htaccess` и разрешите `AllowOverride` для каталога.
- Без `mod_rewrite`: настройте `FallbackResource /index.php` (Apache `mod_dir`) для URL, у которых нет физического файла. Проверьте, что для FPM передаётся исходный путь и корректный front controller.

Пример конфигурации VirtualHost **без mod_rewrite**:

```apache
<VirtualHost *:80>
    ServerName domain.ru
    DocumentRoot /var/www/domain.ru/public_html
    <Directory /var/www/domain.ru/public_html>
        Require all granted
        Options -Indexes
        AllowOverride None
        DirectoryIndex index.php
        FallbackResource /index.php
    </Directory>
</VirtualHost>
```

При `AllowOverride None` правила `.htaccess` не применяются, поэтому защита/маршрутизация должна быть обеспечена VirtualHost. При работе Apache + FPM добавьте соответствующий `SetHandler`/ProxyPassMatch по документации PHP вашего провайдера, не публикуя приватную директорию.

## 4. LiteSpeed / OpenLiteSpeed (LSAPI)

LiteSpeed поддерживает Apache-подобные rewrite-правила, но конкретное место их задания зависит от панели и режима. Укажите DocumentRoot на каталог `public_html` вашей установки (в примере — `/var/www/domain.ru/public_html`); подключите PHP (LSAPI/lsphp), включите **Rewrite**, и либо разрешите чтение `.htaccess`, либо перенесите правила в настройки виртуального хоста.

Пример правил для поля **Rewrite Rules** в виртуальном хосте OpenLiteSpeed:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L,QSA]
```

Для OpenLiteSpeed в настройках Virtual Host → **Rewrite** включите `Enable Rewrite: Yes`, при использовании поставляемого `.htaccess` также `Auto Load from .htaccess: Yes`; для LiteSpeed Enterprise у хостера эти параметры могут задаваться в панели. Не нужны одновременно два дублирующих набора правил. Внешние URL Media должны попадать в `index.php`, а файлы из `public_html/assets` без физического PHP — отдаваться статикой.

## Контрольный список после настройки

| Адрес/проверка | Ожидаемый результат |
|---|---|
| `/install` на чистой БД | Мастер установки (после настройки администратора — закрыт) |
| `/login`, `/account` | Рабочие маршруты; `/account` требует входа |
| `/assets/vendor/bootstrap/css/bootstrap.min.css` | HTTP 200, CSS |
| `/assets/uploads/media/ГГГГ/ММ/<hash>.jpg` | HTTP 200 для существующей Media, 404 для отсутствующей |
| `/app/Config/Database.php`, `/.env`, `/writable/...`, `/vendor/autoload.php` | Нет доступа к исходникам/секретам: 403 или 404 |
| `/assets/uploads/media/... .php` | 404, PHP-код не исполняется |

Если провайдер **не позволяет** разместить приватные файлы отдельно от DocumentRoot или применить нужную маршрутизацию, такой тариф не подходит для безопасной установки. Никакие rewrite-правила не заменят защиту каталога приложения. Дополнительные ограничения резервирования описаны в [инструкции по восстановлению](recovery.md).
