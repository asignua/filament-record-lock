<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Articles\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Workbench\App\Filament\Resources\Articles\ArticleResource;

class ViewArticle extends ViewRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
