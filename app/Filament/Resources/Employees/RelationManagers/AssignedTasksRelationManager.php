<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\Task;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignedTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'assignedTasks';

    protected static ?string $title = 'Assigned Tasks';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
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
                    ->label('Task Name')
                    ->searchable()
                    ->description(fn (Task $record) => $record->project?->name),
                TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ]),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'in_progress',
                        'info' => 'on_hold',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->description(function (Task $record): ?string {
                        if ($record->isOverdue()) {
                            $days = $record->days_overdue ?? 1;

                            return "{$days}d overdue";
                        }

                        return null;
                    })
                    ->color(fn (Task $record) => $record->isOverdue() ? 'danger' : null),
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
