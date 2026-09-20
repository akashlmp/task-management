<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role')
                    ->options([
                        'lead' => 'Project Lead / Tech Lead',
                        'developer' => 'Software Engineer / Developer',
                        'qa' => 'QA Engineer',
                        'designer' => 'UI/UX Designer',
                        'contributor' => 'Contributor',
                    ])
                    ->default('developer')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('employee_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Assigned Role')
                    ->badge()
                    ->colors([
                        'danger' => 'lead',
                        'success' => 'developer',
                        'warning' => 'qa',
                        'info' => 'designer',
                        'gray' => 'contributor',
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'lead' => 'Lead',
                        'developer' => 'Developer',
                        'qa' => 'QA Engineer',
                        'designer' => 'Designer',
                        default => ucfirst($state ?? 'Contributor'),
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->options([
                                'lead' => 'Project Lead / Tech Lead',
                                'developer' => 'Software Engineer / Developer',
                                'qa' => 'QA Engineer',
                                'designer' => 'UI/UX Designer',
                                'contributor' => 'Contributor',
                            ])
                            ->default('developer')
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
