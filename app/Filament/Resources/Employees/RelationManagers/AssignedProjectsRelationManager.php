<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignedProjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocatedProjects';

    protected static ?string $title = 'Allocated Projects';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->required(),
                TextInput::make('allocation_percentage')
                    ->label('Allocation %')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->required(),
                TextInput::make('role')
                    ->default('Developer')
                    ->required(),
                DatePicker::make('start_date'),
                DatePicker::make('end_date'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Project')
                    ->searchable(),
                TextColumn::make('client_company')
                    ->label('Client')
                    ->placeholder('Internal'),
                TextColumn::make('pivot.allocation_percentage')
                    ->label('Allocation')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge()
                    ->color(fn ($state) => (int) $state >= 50 ? 'warning' : 'success'),
                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'planning',
                        'info' => 'in_progress',
                        'warning' => 'on_hold',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge(),
                TextColumn::make('deadline')
                    ->date(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
