<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Filament\Facades\Filament;

class SmokeTest extends TestCase
{
    public function test_the_panel_boots(): void
    {
        $this->assertSame('admin', Filament::getCurrentPanel()?->getId());
    }

    public function test_the_plugin_is_registered_on_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('asignua-filament-record-lock'));
        $this->assertInstanceOf(RecordLockPlugin::class, $panel->getPlugin('asignua-filament-record-lock'));
    }

    public function test_the_translations_are_loaded(): void
    {
        $this->assertSame('Back to list', __('filament-record-lock::filament-record-lock.modal.back'));
    }
}
