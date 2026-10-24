<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Articles;

use Asignua\FilamentRecordLock\Actions\ReleaseLockAction;
use Asignua\FilamentRecordLock\Tables\Columns\RecordLockColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Filament\Resources\Articles\Pages\CreateArticle;
use Workbench\App\Filament\Resources\Articles\Pages\EditArticle;
use Workbench\App\Filament\Resources\Articles\Pages\ListArticles;
use Workbench\App\Filament\Resources\Articles\Pages\ViewArticle;
use Workbench\App\Models\Article;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Textarea::make('body')->rows(8),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withRecordLock())
            ->columns([
                RecordLockColumn::make('lock'),
                TextColumn::make('title')->searchable(),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ReleaseLockAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'view' => ViewArticle::route('/{record}'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
