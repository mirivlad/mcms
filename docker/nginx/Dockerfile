FROM node:24-alpine AS ui

WORKDIR /ui
RUN npm init -y \
    && npm install --save-exact --ignore-scripts --omit=optional --no-audit --no-fund \
        @tabler/core@1.6.1 @tabler/icons-webfont@3.48.0 bootstrap@5.3.8 \
    && mkdir -p \
        /out/tabler \
        /out/tabler-icons/fonts \
        /out/bootstrap/css \
        /out/bootstrap/js \
    && cp node_modules/@tabler/core/dist/css/tabler.min.css /out/tabler/tabler.min.css \
    && cp node_modules/@tabler/core/dist/js/tabler.min.js /out/tabler/tabler.min.js \
    && cp node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css /out/tabler-icons/tabler-icons.min.css \
    && cp node_modules/@tabler/icons-webfont/dist/fonts/tabler-icons.* /out/tabler-icons/fonts/ \
    && cp node_modules/bootstrap/dist/css/bootstrap.min.css /out/bootstrap/css/bootstrap.min.css \
    && cp node_modules/bootstrap/dist/js/bootstrap.bundle.min.js /out/bootstrap/js/bootstrap.bundle.min.js

FROM alpine:3.22 AS theme-assets

WORKDIR /themes
COPY themes/ ./
RUN set -eux; \
    mkdir -p /out; \
    for theme in *; do \
        [ -d "$theme/assets" ] || continue; \
        mkdir -p "/out/$theme"; \
        cp -a "$theme/assets/." "/out/$theme/"; \
    done

FROM nginx:stable-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public/ /var/www/html/public/
COPY --from=ui /out/ /var/www/html/public/assets/vendor/
COPY --from=theme-assets /out/ /var/www/html/public/assets/themes/
COPY LICENSE /usr/share/licenses/mcms/LICENSE
COPY THIRD_PARTY_NOTICES.md /usr/share/licenses/mcms/THIRD_PARTY_NOTICES.md
