<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock;

use Asignua\FilamentRecordLock\Commands\PruneCommand;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Illuminate\Console\Scheduling\Schedule;
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
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (!(bool) config('filament-record-lock.schedule.enabled', true)) {
                return;
            }

            $cron = config('filament-record-lock.schedule.cron', '*/10 * * * *');

            $schedule->command('record-lock:prune')->cron(is_string($cron) ? $cron : '*/10 * * * *');
        });
    }
}
