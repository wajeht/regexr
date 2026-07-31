# regexr

Privacy-focused self-hosted image for [gskinner/regexr](https://github.com/gskinner/regexr), deployed at [regex.jaw.dev](https://regex.jaw.dev).

## Changes from upstream

- Pins upstream commit `d18630d02372b38614f220576bd1888326cf8e78`.
- Keeps browser JavaScript and server-side PHP/PCRE matching.
- Removes analytics, advertising, accounts, community storage, and remote save/share.
- Runs statelessly as an unprivileged user with a read-only root filesystem.

The image and modifications are licensed under GPL-3.0-only, matching upstream.

## Usage

```yaml
image: ghcr.io/wajeht/regexr:<tag>
```

## Updates

Pull requests and main pushes validate the `linux/amd64` image. Version tags publish `linux/amd64` and `linux/arm64` images, create a GitHub release, and update `apps/regexr` in home-ops.

Automatic deployment requires a `GH_TOKEN` Actions secret with access to home-ops and GHCR.
