# Pixelfed Private

<p align="center">
<a href="https://github.com/astr0n8t/pixelfed-private/actions/"><img src="https://github.com/astr0n8t/pixelfed-private/actions/workflows/docker.yml/badge.svg" alt="Docker Build"></a>
</p>

## Introduction

This is a fork of Pixelfed Glitch.

Pixelfed is a free and ethical photo sharing platform, powered by ActivityPub federation.

This fork enables you to run a private/restricted instance without federation requiring an account to view and with working user invites.  The official mobile apps are still compatible while remaining restricted.

<p align="center">
<img src="https://pixelfed.nyc3.cdn.digitaloceanspaces.com/media/pixelfed-screenshot.jpg">
</p>

## Fork specific settings

| Variable | Default | Description |
| --- | --- | --- |
| `RESTRICTED_INSTANCE` | `false` | Require an account to view anything; also hides instance details, nodeinfo and the instance actor from anonymous clients. |
| `PF_USER_INVITES` | `false` | Let users invite people by email from `/i/invites`. |
| `PF_NSFW_ENABLED` | `false` | Show sensitive/NSFW controls in the web UI. |
| `PF_PRONOUNS_ENABLED` | `false` | Show pronoun settings and labels in the web UI. |
| `AUTOFOLLOW_ENABLED` / `AUTOFOLLOW_USERNAMES` | `false` / empty | Default for the admin autofollow settings (comma separated usernames). |

Local media is always served through signed URLs that expire after 60 minutes, so the nginx image routes `/storage` through Laravel.

See [UPSTREAM_SYNC.md](UPSTREAM_SYNC.md) for how to update this fork from Pixelfed Glitch.

## Documentation

There is no official documentation for pixelfed private.

Documentation for Pixelfed Glitch can be found on the [Pixelfed Glitch documentation website](https://pixelfed-glitch.github.io/docs).

Additionally, you can find the official documentation for Pixelfed on the [Pixelfed documentation website](https://pixelfed-glitch.github.io/docs/).

## Run with Docker

More information on : [Running Pixelfed With Docker](https://pixelfed-glitch.github.io/docs/running-pixelfed/docker/prerequisites.html)

## License

Pixelfed, Pixelfed Glitch, and Pixelfed Private are open-sourced software licensed under the AGPL license.

## Communication

Maintainer : @astr0n8t 

## Erata

This fork is for my personal use and you are free to use it or take my features and pull them upstream, but I do not have the time or energy to bother upstreaming my changes.  I have taken the liberty to make opiononated changes to the source code which are probably not compatible with upstream.
