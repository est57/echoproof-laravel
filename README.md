# Echoproof for Laravel

Record real Laravel traffic as [Echoproof](https://github.com/est57/echoproof) snapshots, without running a proxy. Replay them against the base build and the PR build to see which responses a change altered, and whether that was the change you meant.

## Install

```bash
composer require echoproof/laravel
```

Turn recording on in `.env` wherever you want to capture traffic, such as staging or a developer machine:

```dotenv
ECHOPROOF_RECORD=true
```

Every request is appended to `storage/echoproof/traffic.jsonl` (created with mode `0600`) in the terminate phase. Under PHP-FPM that runs after the response has been sent, so clients do not wait for it. Recording is off by default.

## Replay

Use the Echoproof CLI (`brew install est57/tap/echoproof` or `cargo install echoproof`):

```bash
echoproof check -s storage/echoproof/traffic.jsonl \
  --base http://127.0.0.1:8001 -t http://127.0.0.1:8002 \
  --reset "php artisan migrate:fresh --seed --force" --allow-writes "*"
```

Or in CI with the [GitHub Action](https://github.com/est57/echoproof-action). See the [Echoproof README](https://github.com/est57/echoproof) for intent judging and the MCP server for coding agents.

## Configuration

```bash
php artisan vendor:publish --tag=echoproof-config
```

| Key | Default | Meaning |
|---|---|---|
| `enabled` | `ECHOPROOF_RECORD`, `false` | Record traffic. |
| `path` | `ECHOPROOF_PATH`, `storage/echoproof/traffic.jsonl` | Snapshot file. |
| `global` | `true` | Register as global middleware. Set `false` and use the `echoproof` route middleware alias to record selected routes only. |
| `sample_rate` | `ECHOPROOF_SAMPLE_RATE`, `1.0` | Share of requests to record. |
| `only` / `except` | `[]` / Telescope, Horizon, Debugbar, Livewire, `up` | Path patterns (`Str::is`, no leading slash). |
| `max_body_bytes` | 8 MB | Larger exchanges are skipped. |
| `redact_headers` / `redact_fields` | Same as the CLI | Values replaced with `[REDACTED]` before writing. |

Keep the redaction lists in sync with `[sanitize]` in `echoproof.toml`. The defaults match.

## What is skipped

- Multipart uploads, streamed responses and file downloads: they cannot be replayed faithfully.
- Exchanges larger than `max_body_bytes`.
- Requests matching `except`, or not matching `only` when it is set.

A failure to write the snapshot is logged as a warning and never breaks the request.

## Privacy

Recorded traffic can contain personal data even after redaction. Record on staging or test environments where you can, review the redaction lists for your domain (national ID numbers, phone numbers and so on), and do not commit snapshots taken from production.

## License

MIT or Apache-2.0, at your option.
