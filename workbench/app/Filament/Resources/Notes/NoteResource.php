<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Notes;

use Asignua\FilamentRecordLock\Tables\Columns\RecordLockColumn;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Notes\Pages\ManageNotes;
use Workbench\App\Models\Note;

/**
 * Edited in a modal on a single page: the UsesSimpleResourceLock variant, on a model with a UUID key.
 */
class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                RecordLockColumn::make('lock'),
                TextColumn::make('title'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNotes::route('/')];
    }
}
