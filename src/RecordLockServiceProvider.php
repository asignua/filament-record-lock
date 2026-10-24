<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class RecordLockServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-record-lock';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-record-lock.php and are read as
        // `__('filament-record-lock::filament-record-lock.<key>')`. Publish tag: `filament-record-lock-translations`.
        $package->name(static::$name)
            ->hasTranslations()
            ->hasViews();

        // Add a config file only when the plugin really has options: create config/filament-record-lock.php and
        // chain `->hasConfigFile()` here (publish tag `filament-record-lock-config`). Prefer fluent setters on the Plugin.
    }
}
