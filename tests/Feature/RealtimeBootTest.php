<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Support\Realtime;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * Real time switched on BEFORE boot: the provider registers the channel and the auth route.
 */
class RealtimeBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('filament-record-lock.realtime.enabled', true);
        $app['config']->set('broadcasting.default', 'null');
    }

    public function test_the_channel_and_the_auth_route_are_registered(): void
    {
        $this->assertTrue(Realtime::hasAuthRoute());
        $this->assertCount(1, array_filter(
            Route::getRoutes()->getRoutes(),
            fn ($route): bool => $route->uri() === 'broadcasting/auth',
        ));
    }

    public function test_the_channel_pattern_is_registered(): void
    {
        $channels = array_keys($this->app->make(\Illuminate\Contracts\Broadcasting\Factory::class)->connection()->getChannels()->all());

        $this->assertContains('filament-record-lock.{type}.{key}', $channels);
    }
}
