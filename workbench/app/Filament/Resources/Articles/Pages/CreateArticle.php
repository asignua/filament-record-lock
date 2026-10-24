<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Articles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\Articles\ArticleResource;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;
}
