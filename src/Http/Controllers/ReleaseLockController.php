<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Http\Controllers;

use Asignua\FilamentRecordLock\Events\RecordLockChanged;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Support\LockUsers;
use Asignua\FilamentRecordLock\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The target of the page-leave beacon (navigator.sendBeacon). It is an ordinary POST inside the panel's
 * authenticated route group, so the session, the CSRF token (sent in the beacon body) and the panel's access
 * rules apply. It can only delete a lock that belongs to the signed-in user AND to the page instance that
 * sends the holder token, so a forged id changes nothing for anybody else.
 */
final class ReleaseLockController
{
    public function __invoke(Request $request): Response
    {
        $user = LockUsers::current();
        $id = $request->input('lock');
        $holder = $request->input('holder');

        if ($user !== null && (is_string($id) || is_int($id)) && is_string($holder) && $holder !== '') {
            $model = RecordLockPlugin::resolve()->getLockModel();

            $query = $model::query()
                ->whereKey($id)
                ->where('user_id', LockUsers::key($user))
                ->where('holder', $holder);

            // The row is gone after the delete: remember where to send the news (only when it is on).
            $lock = Realtime::enabled() ? (clone $query)->first() : null;

            if ($query->delete() > 0 && $lock !== null) {
                Realtime::announceKey($lock->lockable_type, (string) $lock->lockable_id, RecordLockChanged::REASON_RELEASED);
            }
        }

        return response()->noContent();
    }
}
