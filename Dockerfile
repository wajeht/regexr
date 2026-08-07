ARG NODE_IMAGE=node:24.18.1-bookworm-slim@sha256:235600a8101ab264e117b1768e925532262668dc9b581ef1dd7d96ced463b8e7
ARG PHP_IMAGE=php:8.5.7-cli-alpine3.22@sha256:d4db4138a7adb7cb7b0152b5e12b121c473ea37195170a4c498058fd3b16e480

FROM ${NODE_IMAGE} AS build

RUN apt-get update \
    && apt-get install --yes --no-install-recommends ca-certificates curl patch \
    && rm -rf /var/lib/apt/lists/*

ARG UPSTREAM_COMMIT=d18630d02372b38614f220576bd1888326cf8e78

WORKDIR /src
RUN curl --fail --show-error --location --retry 3 --connect-timeout 15 \
      "https://github.com/gskinner/regexr/archive/${UPSTREAM_COMMIT}.tar.gz" \
      | tar -xz --strip-components=1 -C /src

COPY self-host.patch /tmp/self-host.patch
RUN patch --batch -p1 < /tmp/self-host.patch

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund \
    && NODE_ENV=production ./node_modules/.bin/gulp deploy \
    && rm -rf build/server build/index.php

FROM ${PHP_IMAGE}

LABEL org.opencontainers.image.source="https://github.com/wajeht/regexr" \
      org.opencontainers.image.licenses="GPL-3.0-only" \
      org.opencontainers.image.title="Self-hosted RegExr"

WORKDIR /app

COPY --from=build --chown=www-data:www-data /src/build ./
COPY --chown=www-data:www-data api.php ./server/api.php
COPY --chown=www-data:www-data index.php router.php ./
COPY LICENSE /usr/share/licenses/regexr/LICENSE

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD php -r '$r=@file_get_contents("http://127.0.0.1:8080/healthz"); exit($r === "ok\n" ? 0 : 1);'

CMD ["php", "-d", "expose_php=0", "-d", "display_errors=0", "-d", "log_errors=1", "-d", "max_execution_time=3", "-d", "memory_limit=128M", "-d", "post_max_size=2M", "-S", "0.0.0.0:8080", "router.php"]
