<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Support;

use Asignua\FilamentRecordLock\Events\RecordLockChanged;
use Asignua\FilamentRecordLock\Models\Concerns\HasLocks;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Optional push on top of the heartbeat: the heartbeat still renews the lock, the socket only makes a waiting page
 * ask the server NOW. Off => nothing here runs and the plugin behaves exactly as before.
 */
final class Realtime
{
    /** Broadcasters that deliver to the browser (the rest - log, null - do not). */
    public const array SOCKET_BROADCASTERS = ['reverb', 'pusher', 'ably'];

    public const string CHANNEL = 'filament-record-lock.';

    /** Who puts Laravel Echo into the browser (same keys and semantics as filament-chat / filament-kanban). */
    public const string ECHO_PLUGIN = 'plugin';

    public const string ECHO_FILAMENT = 'filament';

    public const string ECHO_HOST = 'host';

    /**
     * `realtime.enabled`: true/false decides; null (unset) = on when the broadcaster delivers to a browser.
     */
    public static function enabled(): bool
    {
        $enabled = config('filament-record-lock.realtime.enabled');

        if (is_bool($enabled)) {
            return $enabled;
        }

        // env() gives strings: "true" / "false" / "".
        if (is_string($enabled) && $enabled !== '') {
            return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        }

        return in_array(self::broadcaster(), self::SOCKET_BROADCASTERS, true);
    }

    /**
     * The broadcasting connection events go through; null = the app's default one.
     */
    public static function connection(): ?string
    {
        $connection = config('filament-record-lock.realtime.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public static function broadcaster(): ?string
    {
        $connection = self::connection() ?? config('broadcasting.default');

        if (!is_string($connection) || $connection === '') {
            return null;
        }

        $driver = config('broadcasting.connections.'.$connection.'.driver');

        return is_string($driver) && $driver !== '' ? $driver : $connection;
    }

    public static function echoSource(): string
    {
        $source = config('filament-record-lock.realtime.echo', self::ECHO_PLUGIN);

        return in_array($source, [self::ECHO_PLUGIN, self::ECHO_FILAMENT, self::ECHO_HOST], true) ? $source : self::ECHO_PLUGIN;
    }

    /**
     * Echo options for the browser when the plugin brings Echo itself.
     *
     * @return array{broadcaster: string, key: string, cluster: string|null, host: string|null, port: int|null, scheme: string|null}
     */
    public static function echoOptions(): array
    {
        $connection = self::connection() ?? (string) config('broadcasting.default');
        $settings = config('broadcasting.connections.'.$connection);
        $settings = is_array($settings) ? $settings : [];
        $options = is_array($settings['options'] ?? null) ? $settings['options'] : [];
        $client = config('filament-record-lock.realtime.client');
        $client = is_array($client) ? $client : [];
        $port = $client['port'] ?? null;

        return [
            'broadcaster' => (self::broadcaster() ?? 'reverb') === 'reverb' ? 'reverb' : 'pusher',
            'key' => (string) ($settings['key'] ?? ''),
            'cluster' => self::nonEmptyString($options['cluster'] ?? null),
            'host' => self::nonEmptyString($client['host'] ?? null),
            'port' => is_numeric($port) ? (int) $port : null,
            'scheme' => self::nonEmptyString($client['scheme'] ?? null),
        ];
    }

    /**
     * Should the `hooks.echo` view put Echo on the page? Real time on, the plugin brings Echo, and the current panel
     * has this plugin.
     */
    public static function bringsEcho(): bool
    {
        if (!self::enabled() || self::echoSource() !== self::ECHO_PLUGIN) {
            return false;
        }

        $panel = Filament::getCurrentPanel();

        return $panel !== null && $panel->hasPlugin(RecordLockPlugin::ID);
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function registerAuthRoute(): bool
    {
        return (bool) config('filament-record-lock.realtime.register_auth_route', true);
    }

    /**
     * Does the app already serve `/broadcasting/auth`? (Laravel's own `Broadcast::routes()` gives the route no name.)
     */
    public static function hasAuthRoute(): bool
    {
        if (Route::has('broadcasting.auth')) {
            return true;
        }

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'broadcasting/auth') {
                return true;
            }
        }

        return false;
    }

    /**
     * Channel segments are base64url (no dots, no slashes): a class name or a key with odd characters stays valid.
     */
    public static function channelFor(string $type, string $key): string
    {
        return self::CHANNEL.self::encode($type).'.'.self::encode($key);
    }

    /**
     * The private channel the page subscribes to from the observer view (Alpine + Echo), see lock-observer.blade.php.
     */
    public static function channelOf(Model $record): string
    {
        return self::channelFor($record->getMorphClass(), (string) $record->getKey());
    }

    /**
     * Tell the record's channel that its lock changed. Never throws: a dead socket must not break a release.
     */
    public static function announce(Model $record, string $reason, string|int|null $holder = null): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            event(new RecordLockChanged($record->getMorphClass(), (string) $record->getKey(), $reason, $holder));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Like {@see self::announce()} for a lock row whose record is not loaded (the leave beacon).
     */
    public static function announceKey(string $type, string $key, string $reason, string|int|null $holder = null): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            event(new RecordLockChanged($type, $key, $reason, $holder));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Channel auth: the user may listen when they may edit that record - the same question the edit page asks.
     * Unknown / non-lockable types and missing records are refused.
     */
    public static function authorize(Authenticatable $user, string $type, string $key): bool
    {
        $class = self::modelClass(self::decode($type));
        $key = self::decode($key);

        if ($class === null || $key === null) {
            return false;
        }

        $record = (new $class)->newQuery()->whereKey($key)->first();

        if (!$record instanceof Model) {
            return false;
        }

        [$resource, $panel] = self::resourceOf($record);

        if ($resource === null) {
            // No Filament resource edits it: fall back to the model policy (fails closed without one).
            return Gate::forUser($user)->allows('update', $record);
        }

        // The auth request is not a panel request: ask the resource as the panel's guard sees the user.
        $guard = $panel?->getAuthGuard();
        $previous = Auth::getDefaultDriver();

        try {
            if (is_string($guard)) {
                Auth::shouldUse($guard);
            }

            return (bool) $resource::canEdit($record);
        } finally {
            Auth::shouldUse($previous);
        }
    }

    /**
     * The guard of the first panel that has the plugin (the channel is registered once, for all panels).
     */
    public static function guard(): ?string
    {
        foreach (Filament::getPanels() as $panel) {
            if ($panel->hasPlugin(RecordLockPlugin::ID)) {
                return $panel->getAuthGuard();
            }
        }

        return null;
    }

    /**
     * @return class-string<Model>|null
     */
    private static function modelClass(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
            return null;
        }

        return in_array(HasLocks::class, class_uses_recursive($class), true) ? $class : null;
    }

    /**
     * @return array{0: class-string<\Filament\Resources\Resource>|null, 1: Panel|null}
     */
    private static function resourceOf(Model $record): array
    {
        foreach (Filament::getPanels() as $panel) {
            if (!$panel->hasPlugin(RecordLockPlugin::ID)) {
                continue;
            }

            /** @var class-string<\Filament\Resources\Resource>|null $resource */
            $resource = $panel->getModelResource($record);

            if ($resource !== null) {
                return [$resource, $panel];
            }
        }

        return [null, null];
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
