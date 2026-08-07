# RegExr

[![RegExr CI](https://github.com/wajeht/regexr/actions/workflows/publish.yml/badge.svg?branch=main)](https://github.com/wajeht/regexr/actions/workflows/publish.yml) [![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://github.com/wajeht/regexr/blob/main/LICENSE) [![Open Source Love svg1](https://badges.frapsoft.com/os/v1/open-source.svg?v=103)](https://github.com/wajeht/regexr)

a privacy-focused, self-hosted version of [RegExr](https://github.com/gskinner/regexr)

# Usage

use it at [regex.jaw.dev](https://regex.jaw.dev), or run it locally:

```bash
docker run --rm \
  --publish 8080:8080 \
  --read-only \
  --tmpfs /tmp \
  --cap-drop ALL \
  --security-opt no-new-privileges \
  ghcr.io/wajeht/regexr:latest
```

then open [localhost:8080](http://localhost:8080).

## How it works

1. Builds the upstream RegExr web app from a pinned commit.
2. Runs JavaScript regular expressions in the browser.
3. Uses a small PHP endpoint for server-side PCRE matching.
4. Removes analytics, ads, accounts, community storage, and remote save/share features.
5. Runs statelessly as an unprivileged user with a read-only filesystem.

## API Endpoints

### GET /

Serves the RegExr application.

### POST /server/api.php

Internal endpoint used by the application for PCRE matching.

### GET /healthz

Health check endpoint. Returns `ok` when the service is healthy.

## License

Distributed under the GPL-3.0-only License, matching upstream RegExr. See [LICENSE](./LICENSE) for more information.
