<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\StatusHistoriesRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\WorkLogsRelationManager;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'manager']) || $user->can('tasks.create'));
    }

    public static function form(Schema $schema): Schema
    {
        $isManager = auth()->user()?->hasRole(['admin', 'manager']) ?? false;

        return $schema
            ->components([
                Select::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'name', modifyQueryUsing: fn (Builder $q) => $q->active())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(! $isManager),
                TextInput::make('name')
                    ->label('Task Name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(! $isManager),
                TextInput::make('code')
                    ->label('Task Code')
                    ->placeholder('e.g. TSK-001')
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase;'])
                    ->disabled(! $isManager),
                Select::make('assigned_employee_id')
                    ->label('Assignee')
                    ->relationship('assignedEmployee', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->disabled(! $isManager),
                Select::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ])
                    ->default('medium')
                    ->required()
                    ->disabled(! $isManager),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'on_hold' => 'On Hold',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state === 'completed') {
                            $set('progress', 100);
                        }
                    }),
                TextInput::make('progress')
                    ->label('Progress (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->default(0)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ((int) $state === 100) {
                            $set('status', 'completed');
                        }
                    }),
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->default(now())
                    ->disabled(! $isManager),
                DatePicker::make('due_date')
                    ->label('Due Date')
                    ->disabled(! $isManager),
                TextInput::make('estimated_hours')
                    ->label('Estimated Hours')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('hrs')
                    ->disabled(! $isManager),
                TextInput::make('actual_hours')
                    ->label('Actual Hours Spent')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('hrs'),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->disabled(! $isManager),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export All to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (): StreamedResponse {
                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['Code', 'Task Name', 'Project', 'Assignee', 'Priority', 'Status', 'Progress', 'Due Date', 'Est Hours', 'Actual Hours']);

                            Task::with(['project', 'assignedEmployee'])->chunk(100, function ($tasks) use ($handle) {
                                foreach ($tasks as $task) {
                                    fputcsv($handle, [
                                        $task->code,
                                        $task->name ?? $task->title,
                                        $task->project?->name ?? '',
                                        $task->assignedEmployee?->name ?? 'Unassigned',
                                        $task->priority,
                                        $task->status,
                                        "{$task->progress}%",
                                        $task->due_date?->format('Y-m-d') ?? '',
                                        $task->estimated_hours,
                                        $task->actual_hours,
                                    ]);
                                }
                            });

                            fclose($handle);
                        }, 'tasks-report-' . now()->format('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Task Name')
                    ->getStateUsing(fn (Task $record): string => $record->name ?? $record->title ?? '')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('assignedEmployee.name')
                    ->label('Assignee')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ])
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'in_progress',
                        'info' => 'on_hold',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),
                TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge()
                    ->color(function (int $state): string {
                        if ($state >= 100) {
                            return 'success';
                        }
                        if ($state >= 50) {
                            return 'warning';
                        }

                        return 'gray';
                    })
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn (Task $record) => $record->isOverdue() ? 'danger' : null)
                    ->description(function (Task $record): ?string {
                        if ($record->isOverdue()) {
                            $days = $record->days_overdue ?? 1;

                            return "{$days}d overdue";
                        }

                        return null;
                    }),
                TextColumn::make('estimated_hours')
                    ->label('Est. Hrs')
                    ->suffix('h')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_hours')
                    ->label('Actual Hrs')
                    ->suffix('h')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('overdue')
                    ->label('Overdue Tasks')
                    ->query(fn (Builder $query) => $query->overdue()),
                SelectFilter::make('project')
                    ->relationship('project', 'name'),
                SelectFilter::make('assigned_employee_id')
                    ->label('Assignee')
                    ->relationship('assignedEmployee', 'name'),
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'on_hold' => 'On Hold',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (Task $record): bool => in_array($record->status, ['pending', 'on_hold']))
                    ->action(function (Task $record): void {
                        $record->update(['status' => 'in_progress']);
                        Notification::make()
                            ->title('Task started')
                            ->success()
                            ->send();
                    }),
                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Task $record): bool => $record->status !== 'completed')
                    ->action(function (Task $record): void {
                        $record->update(['status' => 'completed', 'progress' => 100]);
                        Notification::make()
                            ->title('Task completed!')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_selected')
                        ->label('Export Selected to CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records): StreamedResponse {
                            return response()->streamDownload(function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['Code', 'Task Name', 'Project', 'Assignee', 'Priority', 'Status', 'Progress', 'Due Date']);
                                foreach ($records as $task) {
                                    fputcsv($handle, [
                                        $task->code,
                                        $task->name ?? $task->title,
                                        $task->project?->name ?? '',
                                        $task->assignedEmployee?->name ?? 'Unassigned',
                                        $task->priority,
                                        $task->status,
                                        "{$task->progress}%",
                                        $task->due_date?->format('Y-m-d') ?? '',
                                    ]);
                                }
                                fclose($handle);
                            }, 'selected-tasks-' . now()->format('Y-m-d') . '.csv', [
                                'Content-Type' => 'text/csv',
                            ]);
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->hasRole('admin')) {
            return $query;
        }

        if ($user->hasRole('manager')) {
            return $query;
        }

        // Employee role: scoped to auth()->user()->id
        return $query->where('assigned_employee_id', $user->id);
    }

    public static function getRelations(): array
    {
        return [
            WorkLogsRelationManager::class,
            CommentsRelationManager::class,
            StatusHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
