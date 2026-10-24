<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Tests\TestCase;
use Illuminate\Support\Arr;

class TranslationsTest extends TestCase
{
    private const array LOCALES = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pl', 'pt_BR', 'tr', 'uk'];

    public function test_every_locale_has_the_same_keys_as_english(): void
    {
        $english = $this->keys('en');

        $this->assertNotEmpty($english);

        foreach (self::LOCALES as $locale) {
            $this->assertSame($english, $this->keys($locale), "Locale [{$locale}] is out of step with [en].");
        }
    }

    public function test_every_locale_keeps_the_placeholders_of_english(): void
    {
        $english = Arr::dot(require __DIR__.'/../../resources/lang/en/filament-record-lock.php');

        foreach (self::LOCALES as $locale) {
            $strings = Arr::dot(require __DIR__.'/../../resources/lang/'.$locale.'/filament-record-lock.php');

            foreach ($english as $key => $text) {
                preg_match_all('/:[a-z]+/', (string) $text, $expected);
                preg_match_all('/:[a-z]+/', (string) $strings[$key], $actual);

                $this->assertEqualsCanonicalizing($expected[0], $actual[0], "[{$locale}] {$key}");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $path = __DIR__.'/../../resources/lang/'.$locale.'/filament-record-lock.php';

        $this->assertFileExists($path);

        $keys = array_keys(Arr::dot(require $path));
        sort($keys);

        return $keys;
    }
}
