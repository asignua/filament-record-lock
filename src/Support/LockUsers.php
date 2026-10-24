<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Who is the user - the model, the current one, the display name. Everything goes through the panel's auth guard
 * when a panel is current, otherwise through the default guard.
 */
final class LockUsers
{
    /**
     * @return class-string<Model>
     */
    public static function model(): string
    {
        $configured = config('filament-record-lock.models.user');

        if (is_string($configured) && is_a($configured, Model::class, true)) {
            return $configured;
        }

        $guard = Filament::getCurrentPanel()?->getAuthGuard() ?? config('auth.defaults.guard');
        $provider = config('auth.guards.'.(is_string($guard) ? $guard : 'web').'.provider');
        $model = is_string($provider) ? config('auth.providers.'.$provider.'.model') : null;

        /** @var class-string<Model> */
        return is_string($model) && is_a($model, Model::class, true) ? $model : 'App\\Models\\User';
    }

    public static function current(): ?Authenticatable
    {
        $panel = Filament::getCurrentPanel();

        return $panel !== null ? $panel->auth()->user() : Auth::user();
    }

    public static function key(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }

    public static function name(Authenticatable|Model|null $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return Filament::getUserName($user);
    }
}
