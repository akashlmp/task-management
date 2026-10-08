<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Project Tasks';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Task Name')
                    ->required()
                    ->maxLength(255),
                Select::make('assigned_employee_id')
                    ->label('Assigned Employee')
                    ->relationship('assignedEmployee', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ])
                    ->default('medium')
                    ->required(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'on_hold' => 'On Hold',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required(),
                TextInput::make('progress')
                    ->label('Progress (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0),
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->default(now()),
                DatePicker::make('due_date')
                    ->label('Due Date'),
                TextInput::make('estimated_hours')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('hrs'),
                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Project $project */
        $project = $this->getOwnerRecord();
        $isClosed = $project && $project->isClosed();

        return $table
            ->recordTitleAttribute('name')
            ->headerActions([
                CreateAction::make()
                    ->label('Create Task')
                    ->disabled($isClosed)
                    ->tooltip($isClosed ? "Project is {$project?->status}. Tasks cannot be added." : null),
            ])
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Task Name')
                    ->searchable(),
                TextColumn::make('assignedEmployee.name')
                    ->label('Assignee')
                    ->placeholder('Unassigned')
                    ->badge()
                    ->color('gray'),
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
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
