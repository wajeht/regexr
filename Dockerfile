ARG NODE_IMAGE=node:24.18.1-bookworm-slim@sha256:235600a8101ab264e117b1768e925532262668dc9b581ef1dd7d96ced463b8e7
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1-php8.5-bookworm@sha256:52ea80fc6691dd3afb38a9c6a96c8fe817aef6117f7a7a2f3dd308daf8fa0b9a

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

FROM ${FRANKENPHP_IMAGE}

LABEL org.opencontainers.image.source="https://github.com/wajeht/regexr" \
      org.opencontainers.image.licenses="GPL-3.0-only" \
      org.opencontainers.image.title="Self-hosted RegExr"

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && setcap -r /usr/local/bin/frankenphp

ENV XDG_CONFIG_HOME=/tmp/caddy-config \
    XDG_DATA_HOME=/tmp/caddy-data

WORKDIR /app

COPY --from=build /src/build ./
COPY api.php ./server/api.php
COPY index.php ./
COPY --chmod=644 healthz ./
COPY --chmod=644 Caddyfile /etc/frankenphp/Caddyfile
COPY LICENSE /usr/share/licenses/regexr/LICENSE

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD php -r '$r=@file_get_contents("http://127.0.0.1:8080/healthz"); exit($r === "ok\n" ? 0 : 1);'

ENTRYPOINT ["/usr/local/bin/frankenphp"]
CMD ["run", "--config", "/etc/frankenphp/Caddyfile"]
