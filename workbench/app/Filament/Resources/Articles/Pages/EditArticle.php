<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Articles\Pages;

use Asignua\FilamentRecordLock\Resources\Pages\Concerns\UsesResourceLock;
use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\Articles\ArticleResource;

class EditArticle extends EditRecord
{
    use UsesResourceLock;

    protected static string $resource = ArticleResource::class;
}
