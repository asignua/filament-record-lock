<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Tests\TestCase;
use Workbench\App\Models\Article;

/**
 * The model API that kenepa/resource-lock users know.
 */
class HasLocksTest extends TestCase
{
    public function test_lock_and_unlock_for_the_current_user(): void
    {
        $article = Article::query()->create(['title' => 'A']);

        $this->assertTrue($article->isUnlocked());
        $this->assertTrue($article->hasExpiredLock());

        $this->assertTrue($article->lock());
        $this->assertTrue($article->isLocked());
        $this->assertTrue($article->isLockedByCurrentUser());
        $this->assertTrue($article->lockCreatedByCurrentUser());
        $this->assertFalse($article->hasExpiredLock());
        $this->assertSame(auth()->id(), (int) $article->resourceLock->user_id);
        $this->assertTrue($article->recordLock->is($article->resourceLock));

        $this->assertTrue($article->unlock());
        $this->assertTrue($article->isUnlocked());
    }

    public function test_another_users_lock_is_respected_until_forced_or_expired(): void
    {
        $article = Article::query()->create(['title' => 'A']);
        $this->assertTrue($article->lock());

        $this->actingAs($this->userNamed('Noah'));

        $this->assertTrue($article->isLocked());
        $this->assertFalse($article->isLockedByCurrentUser());
        $this->assertFalse($article->lock());
        $this->assertFalse($article->unlock());
        $this->assertTrue($article->isLocked());

        $this->assertTrue($article->unlock(force: true));
        $this->assertTrue($article->isUnlocked());
        $this->assertTrue($article->lock());
    }

    public function test_an_expired_lock_is_unlocked_by_anyone(): void
    {
        config(['filament-record-lock.lock_timeout' => 30]);
        $article = Article::query()->create(['title' => 'A']);
        $article->lock();

        $this->actingAs($this->userNamed('Noah'));
        $this->travel(60)->seconds();

        $this->assertTrue($article->isUnlocked());
        $this->assertTrue($article->hasExpiredLock());
        $this->assertTrue($article->unlock());
        $this->assertTrue($article->lock());
    }

    public function test_the_with_record_lock_scope_eager_loads_the_lock_and_its_holder(): void
    {
        Article::query()->create(['title' => 'A'])->lock();

        $article = Article::query()->withRecordLock()->first();

        $this->assertTrue($article->relationLoaded('recordLock'));
        $this->assertTrue($article->recordLock->relationLoaded('user'));
    }
}
