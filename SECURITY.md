# Security Policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 1.x     | ✅        |
| < 1.0   | ❌        |

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Report it privately through GitHub: open the
[Security tab](https://github.com/asignua/filament-record-lock/security) of this repository and click
**Report a vulnerability**. Include the affected version, steps to reproduce and the impact you see.

You can expect an acknowledgement within 3 working days and a status update within 7 days.
Once a fix is released, the advisory is published and you are credited unless you prefer otherwise.

## Real-time channel (optional)

With `realtime.enabled`, each record has a private broadcasting channel (`filament-record-lock.{type}.{key}`). Channel
authorisation fails closed: the model must use `HasLocks`, the record must exist, and the user must pass
`Resource::canEdit()` of the panel that has the plugin (or the model's `update` policy when no resource edits it);
unknown types and missing records are refused. Events carry identifiers only (type, key, reason, holder id) - the page
re-reads the real state from the server, so a spoofed or replayed message can at worst trigger an extra heartbeat.
