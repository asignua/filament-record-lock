<?php

declare(strict_types=1);

use Asignua\FilamentRecordLock\Models\RecordLock;

return [
    /*
     * Database connection and table of the locks. `null` = the default connection.
     */
    'connection' => env('RECORD_LOCK_CONNECTION'),

    'table' => env('RECORD_LOCK_TABLE', 'record_locks'),

    'models' => [
        /*
         * The user model that holds locks. `null` = the model of the panel's auth guard (auth.providers.*.model).
         */
        'user' => null,

        /*
         * Swap in your own lock model (it must extend the package model).
         */
        'lock' => RecordLock::class,
    ],

    /*
     * Seconds a lock lives without a heartbeat. Keep it at least twice the polling interval, otherwise a lock
     * can expire between two heartbeats of a user who is still typing.
     */
    'lock_timeout' => (int) env('RECORD_LOCK_TIMEOUT', 120),

    /*
     * Refuse a save from a user who no longer holds the lock (the lock expired and someone else took the record,
     * or an admin took it over). Recommended; turn off only if you accept last-write-wins.
     */
    'check_locks_before_saving' => true,

    /*
     * The heartbeat: the page polls the server to renew the lock and to notice a takeover. Without it the lock
     * only lives `lock_timeout` seconds after the page was opened.
     */
    'polling' => [
        'enabled' => true,
        'interval' => 15,
        // wire:poll.keep-alive - keep polling in a background tab (Livewire slows hidden tabs down otherwise).
        'keep_alive' => false,
        // wire:poll.visible - poll only while the element is in the viewport.
        'visible' => false,
    ],

    'lock_notice' => [
        // Show the name of the user who holds the lock in the blocking notice.
        'display_owner' => true,
    ],

    /*
     * Who may press "Unlock and edit" (and release locks in the manager / a table). With `limited_access` on
     * (the default) the answer comes from the gate ability below - and, when the ability is not defined, from
     * NOBODY. Set `limited_access` to false to let every user who can edit the record take it over.
     */
    'unlocker' => [
        'limited_access' => true,
        'ability' => 'unlockRecordLock',
    ],

    /*
     * The read-only "Record locks" resource. Closed by default, like the unlocker.
     */
    'manager' => [
        'enabled' => true,
        'limited_access' => true,
        'ability' => 'viewRecordLocks',
        'should_register_navigation' => true,
        'navigation_badge' => false,
        'navigation_icon' => 'heroicon-o-lock-closed',
        'navigation_group' => null,
        'navigation_sort' => null,
    ],

    /*
     * Registers `record-lock:prune` in the application schedule. Set `enabled` to false to schedule it yourself.
     */
    'schedule' => [
        'enabled' => env('RECORD_LOCK_SCHEDULE', true),
        'cron' => '*/10 * * * *',
    ],
];
