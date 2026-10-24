<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Support\RecordLocker;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Workbench\App\Models\Article;

/**
 * The target of the page-leave beacon.
 */
class ReleaseRouteTest extends TestCase
{
    private const string URL = '/admin/record-lock/release';

    public function test_the_page_instance_that_took_the_lock_releases_it(): void
    {
        $emma = $this->userNamed('Emma');
        $article = Article::query()->create(['title' => 'A']);
        $lock = app(RecordLocker::class)->acquire($article, $emma, 'page-1');

        $this->actingAs($emma)
            ->post(self::URL, ['lock' => $lock->getKey(), 'holder' => 'page-1'])
            ->assertNoContent();

        $this->assertNull(app(RecordLocker::class)->find($article));
    }

    public function test_another_tab_or_a_stale_unload_does_not_drop_the_lock(): void
    {
        $emma = $this->userNamed('Emma');
        $article = Article::query()->create(['title' => 'A']);
        // The reloaded page took the lock again under a new holder token, then the old page's beacon arrives.
        app(RecordLocker::class)->acquire($article, $emma, 'old-page');
        $lock = app(RecordLocker::class)->acquire($article, $emma, 'new-page');

        $this->actingAs($emma)
            ->post(self::URL, ['lock' => $lock->getKey(), 'holder' => 'old-page'])
            ->assertNoContent();

        $this->assertNotNull(app(RecordLocker::class)->active($article));
    }

    public function test_a_user_cannot_release_somebody_elses_lock_even_with_the_holder_token(): void
    {
        $emma = $this->userNamed('Emma');
        $noah = $this->userNamed('Noah');
        $article = Article::query()->create(['title' => 'A']);
        $lock = app(RecordLocker::class)->acquire($article, $emma, 'page-1');

        $this->actingAs($noah)
            ->post(self::URL, ['lock' => $lock->getKey(), 'holder' => 'page-1'])
            ->assertNoContent();

        $this->assertTrue(app(RecordLocker::class)->heldBy($article, $emma));
    }

    public function test_a_guest_is_sent_to_the_login(): void
    {
        $this->app['auth']->guard('web')->logout();

        $this->post(self::URL, ['lock' => 1, 'holder' => 'x'])->assertRedirect();
    }

    public function test_garbage_input_is_ignored(): void
    {
        $this->post(self::URL, ['lock' => ['x'], 'holder' => ['y']])->assertNoContent();
        $this->post(self::URL)->assertNoContent();
    }

    public function test_a_real_page_request_renders_the_observer_with_a_csrf_token_and_takes_the_lock(): void
    {
        $emma = $this->userNamed('Emma');
        $article = Article::query()->create(['title' => 'A']);

        $this->actingAs($emma)
            ->get('/admin/articles/'.$article->getKey().'/edit')
            ->assertOk()
            ->assertSee('record-lock-observer', false)
            ->assertSee('record-lock\/release', false)
            ->assertDontSee('token: null', false);

        $this->assertTrue(app(RecordLocker::class)->heldBy($article, $emma));
    }
}
