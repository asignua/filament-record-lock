<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Http\Controllers;

use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Support\LockUsers;
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

            $model::query()
                ->whereKey($id)
                ->where('user_id', LockUsers::key($user))
                ->where('holder', $holder)
                ->delete();
        }

        return response()->noContent();
    }
}
