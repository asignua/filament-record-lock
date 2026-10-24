<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\User;

/**
 * The demo's authorization: emma@example.com is the administrator who may take locks over and open the manager,
 * noah@example.com is an ordinary editor. Not registered by the test case, so the tests see no gates.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('unlockRecordLock', fn (User $user): bool => $user->email === 'emma@example.com');
        Gate::define('viewRecordLocks', fn (User $user): bool => $user->email === 'emma@example.com');
    }
}
