<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\Article;
use Workbench\App\Models\Note;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['emma' => 'Emma', 'noah' => 'Noah'] as $slug => $name) {
            User::query()->firstOrCreate(['email' => "{$slug}@example.com"], [
                'name' => $name,
                'password' => 'password',
                'is_active' => true,
            ]);
        }

        foreach (['Launch plan', 'Pricing page', 'Release notes', 'Onboarding guide', 'Press kit'] as $title) {
            Article::query()->firstOrCreate(['title' => $title], ['body' => "Draft of the \"{$title}\" article."]);
        }

        foreach (['Standup agenda', 'Reading list', 'Ideas'] as $title) {
            Note::query()->firstOrCreate(['title' => $title]);
        }
    }
}
