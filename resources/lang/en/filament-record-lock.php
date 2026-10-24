<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'This record is being edited',
        'heading_taken_over' => 'Your lock was taken over',
        'heading_stale' => 'The record has changed',
        'locked' => ':user has been editing this record since :since. Wait until they are done, or open it read-only.',
        'locked_unknown' => 'Another user is editing this record. Wait until they are done, or open it read-only.',
        'taken_over' => ':user took this record over. Your unsaved changes can no longer be saved here.',
        'taken_over_unknown' => 'Another user took this record over. Your unsaved changes can no longer be saved here.',
        'stale' => 'Someone changed and saved this record while your lock had lapsed. Reload it to continue with the current data.',
        'back' => 'Back to list',
        'view' => 'View read-only',
        'unlock' => 'Unlock and edit',
        'reload' => 'Reload the record',
    ],
    'notifications' => [
        'taken_over_title' => 'Your lock was taken over',
        'taken_over_body' => 'Another user took this record over. Your unsaved changes can no longer be saved.',
        'save_refused_title' => 'Not saved',
        'save_refused_body' => 'You no longer hold the lock on this record, so your changes were not saved.',
        'unlock_denied' => 'You are not allowed to unlock records.',
    ],
    'actions' => [
        'release' => 'Release lock',
        'release_heading' => 'Release this lock?',
        'release_description' => 'The person editing the record loses the lock and cannot save their changes.',
        'released' => 'Lock released',
        'release_selected' => 'Release locks',
        'release_selected_heading' => 'Release the selected locks?',
        'released_count' => 'Locks released: :count',
        'release_all' => 'Release all locks',
        'release_all_description' => 'Every lock is deleted and everyone who is editing a record loses it.',
        'released_all' => 'All locks released',
    ],
    'resource' => [
        'navigation' => 'Record locks',
        'singular' => 'record lock',
        'plural' => 'Record locks',
    ],
    'table' => [
        'type' => 'Type',
        'record' => 'Record',
        'holder' => 'Locked by',
        'since' => 'Locked since',
        'expires' => 'Expires',
        'status' => 'Status',
    ],
    'status' => [
        'active' => 'Active',
        'expired' => 'Expired',
    ],
    'column' => [
        'mine' => 'You are editing this record',
        'other' => ':user is editing this record (since :since)',
        'other_unknown' => 'Locked by another user',
    ],
];
