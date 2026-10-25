# Changelog

All notable changes to `asignua/filament-record-lock` are documented here.

## Unreleased

- Pessimistic record locking for Filament 5: `HasLocks` model trait, `UsesResourceLock` for Edit pages and
  `UsesSimpleResourceLock` for modal edits, a heartbeat that renews the lock, release on leave through
  `navigator.sendBeacon`, a blocking notice with "Back to list", "View read-only" and a gated "Unlock and edit".
- A save from a page that no longer holds the lock is refused; a record changed while the lock had lapsed is detected.
- `RecordLockColumn`, `ReleaseLockAction`, `ReleaseLocksBulkAction`, the read-only "Record locks" manager,
  the `RecordLockTakenOver` event, the `record-lock:prune` command (scheduled).
- Integer, UUID and ULID keys; configurable table and connection; translations in ten languages.
- Review fixes: lock state is `#[Locked]`; renewals no longer rely on affected-row counts (MySQL); string-key eager loading
  (PostgreSQL); own save no longer marks the page stale; all Edit-page actions are lock-guarded; heartbeat re-creates rows
  with the page token; manager closed on tenant panels without `scopeManagerQuery()`; atomic `takeOver()`; unknown
  morph types do not crash the manager; the poll element is pinned to the viewport.
- API-compatible with `kenepa/resource-lock` where it makes sense (see the migration section of the README).
