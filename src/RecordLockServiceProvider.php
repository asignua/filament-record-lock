<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock;

use Asignua\FilamentRecordLock\Commands\PruneCommand;
use Asignua\FilamentRecordLock\Support\Realtime;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class RecordLockServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-record-lock';

    public function configurePackage(Package $package): void
    {
        // Translations: `__('filament-record-lock::filament-record-lock.<key>')`, publish tag `filament-record-lock-translations`.
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasMigration('create_record_locks_table')
            ->hasCommand(PruneCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RecordLocker::class);
    }

    public function packageBooted(): void
    {
        // After every provider (panels included) has booted: the guard comes from the panel that has the plugin.
        $this->app->booted(function (): void {
            if (!Realtime::enabled()) {
                return;
            }

            $guard = Realtime::guard();

            Broadcast::channel(
                Realtime::CHANNEL.'{type}.{key}',
                fn (Authenticatable $user, string $type, string $key): bool => Realtime::authorize($user, $type, $key),
                $guard !== null ? ['guards' => [$guard]] : [],
            );

            // Private channels need /broadcasting/auth; many panels never set broadcasting up.
            if (Realtime::registerAuthRoute() && !Realtime::hasAuthRoute()) {
                Broadcast::routes(['middleware' => ['web', $guard !== null ? 'auth:'.$guard : 'auth']]);
            }
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (!(bool) config('filament-record-lock.schedule.enabled', true)) {
                return;
            }

            $cron = config('filament-record-lock.schedule.cron', '*/10 * * * *');

            $schedule->command('record-lock:prune')->cron(is_string($cron) ? $cron : '*/10 * * * *');
        });
    }
}
