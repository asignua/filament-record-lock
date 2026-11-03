<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Support\Realtime;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

/**
 * The plugin's own Echo bootstrap (`realtime.echo` = 'plugin'), same keys as filament-chat / filament-kanban.
 */
class RealtimeEchoHookTest extends TestCase
{
    private function hook(): string
    {
        return View::make('filament-record-lock::hooks.echo')->render();
    }

    private function hookViaRenderHook(): string
    {
        return Blade::render('{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_AFTER) }}') === '' ? '' : (string) \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_AFTER);
    }

    private function configure(?bool $enabled, string $echo = 'plugin'): void
    {
        config([
            'filament-record-lock.realtime.enabled' => $enabled,
            'filament-record-lock.realtime.echo' => $echo,
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => ['driver' => 'reverb', 'key' => 'app-key', 'options' => []],
        ]);
    }

    public function test_rendered_when_enabled_and_echo_is_plugin(): void
    {
        $this->configure(true);

        $html = $this->hook();

        $this->assertStringContainsString('window.EchoFactory', $html);
        $this->assertStringContainsString('app-key', $html);
    }

    public function test_not_rendered_when_realtime_is_off(): void
    {
        $this->configure(false);

        $this->assertStringNotContainsString('EchoFactory', $this->hook());
        $this->assertStringNotContainsString('EchoFactory', $this->hookViaRenderHook());
    }

    public function test_not_rendered_for_filament_or_host_echo(): void
    {
        $this->configure(true, 'filament');
        $this->assertStringNotContainsString('EchoFactory', $this->hook());

        $this->configure(true, 'host');
        $this->assertStringNotContainsString('EchoFactory', $this->hook());
    }

    public function test_registered_on_the_scripts_hook_of_a_panel_with_the_plugin(): void
    {
        $this->configure(true);

        $this->assertStringContainsString('window.EchoFactory', $this->hookViaRenderHook());
    }

    public function test_echo_is_initialised_once_when_another_plugin_did_it_first(): void
    {
        $this->configure(true);

        // The guard chat and kanban use too: a second plugin reuses the existing instance.
        $this->assertStringContainsString('if (window.Echo || ! window.EchoFactory)', $this->hook());
    }

    public function test_unknown_echo_source_falls_back_to_plugin_and_client_overrides_apply(): void
    {
        $this->configure(true, 'nonsense');
        config(['filament-record-lock.realtime.client' => ['host' => 'ws.example.com', 'port' => '443', 'scheme' => 'https']]);

        $this->assertSame(Realtime::ECHO_PLUGIN, Realtime::echoSource());
        $this->assertSame(
            ['broadcaster' => 'reverb', 'key' => 'app-key', 'cluster' => null, 'host' => 'ws.example.com', 'port' => 443, 'scheme' => 'https'],
            Realtime::echoOptions(),
        );
        $this->assertStringContainsString('ws.example.com', $this->hook());
    }

    public function test_client_address_resolves_plugin_then_vite_reverb_then_filament_then_page(): void
    {
        $key = 'filament-record-lock.realtime.client';
        $none = ['host' => null, 'port' => null, 'scheme' => null];

        // 4. nothing set: the page decides (nulls).
        config([$key => $none, 'filament.broadcasting.echo' => null]);
        $this->assertSame($none, Realtime::clientAddress());

        // 3. Filament's own Echo config.
        config(['filament.broadcasting.echo' => ['wsHost' => 'fil.example.com', 'wsPort' => 6001, 'wssPort' => 6443, 'forceTLS' => true]]);
        $this->assertSame(['host' => 'fil.example.com', 'port' => 6443, 'scheme' => 'https'], Realtime::clientAddress());

        config(['filament.broadcasting.echo' => ['wsHost' => 'fil.example.com', 'wsPort' => '6001', 'forceTLS' => false]]);
        $this->assertSame(['host' => 'fil.example.com', 'port' => 6001, 'scheme' => 'http'], Realtime::clientAddress());

        // 2. VITE_REVERB_* (read by the config file through env()) beats Filament.
        $_SERVER['VITE_REVERB_HOST'] = 'vite.example.com';
        $_SERVER['VITE_REVERB_PORT'] = '8443';
        $_SERVER['VITE_REVERB_SCHEME'] = 'https';

        try {
            $loaded = require __DIR__.'/../../config/filament-record-lock.php';
        } finally {
            unset($_SERVER['VITE_REVERB_HOST'], $_SERVER['VITE_REVERB_PORT'], $_SERVER['VITE_REVERB_SCHEME']);
        }

        $this->assertSame(['host' => 'vite.example.com', 'port' => '8443', 'scheme' => 'https'], $loaded['realtime']['client']);
        config([$key => $loaded['realtime']['client']]);
        $this->assertSame(['host' => 'vite.example.com', 'port' => 8443, 'scheme' => 'https'], Realtime::clientAddress());

        // 1. The plugin's own value beats everything, field by field.
        config([$key => ['host' => 'own.example.com', 'port' => null, 'scheme' => null]]);
        $this->assertSame(['host' => 'own.example.com', 'port' => 6001, 'scheme' => 'http'], Realtime::clientAddress());

        // echoOptions() carries the resolved address.
        $this->assertSame('own.example.com', Realtime::echoOptions()['host']);
    }
}
