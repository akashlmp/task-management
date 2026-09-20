<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\RelationManagers\AssigneesRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\StatusHistoriesRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\WorkLogsRelationManager;
use App\Models\SlaPolicy;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Task Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ])
                    ->default('medium')
                    ->required(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'assigned' => 'Assigned',
                        'in_progress' => 'In Progress',
                        'blocked' => 'Blocked',
                        'review' => 'Review',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required(),
                Select::make('sla_policy_id')
                    ->label('SLA Policy')
                    ->relationship('slaPolicy', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Defaults automatically to matching priority policy')
                    ->nullable(),
                Select::make('parent_task_id')
                    ->label('Parent Task')
                    ->relationship('parentTask', 'title')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('estimated_minutes')
                    ->label('Estimated Duration')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('min')
                    ->nullable(),
                DateTimePicker::make('due_at')
                    ->label('Target Due Date & Time')
                    ->nullable(),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'critical',
                    ])
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'info' => 'assigned',
                        'warning' => 'in_progress',
                        'danger' => 'blocked',
                        'primary' => 'review',
                        'success' => 'completed',
                    ])
                    ->sortable(),
                TextColumn::make('assignees.name')
                    ->label('Assignees')
                    ->badge()
                    ->separator(','),
                TextColumn::make('sla_status')
                    ->label('SLA')
                    ->getStateUsing(function (Task $record): string {
                        $log = $record->slaLog;
                        if (! $log) {
                            return 'Normal';
                        }
                        if ($log->response_breached || $log->resolution_breached) {
                            return 'Breached';
                        }
                        if ($log->response_warning_sent_at || $log->resolution_warning_sent_at) {
                            return 'Warning';
                        }

                        return 'On Track';
                    })
                    ->badge()
                    ->colors([
                        'danger' => 'Breached',
                        'warning' => 'Warning',
                        'success' => 'On Track',
                        'gray' => 'Normal',
                    ]),
                TextColumn::make('due_at')
                    ->label('Due Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('actual_minutes')
                    ->label('Time Spent')
                    ->formatStateUsing(fn (int $state): string => SlaPolicy::formatMinutes($state) . " ({$state}m)")
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'assigned' => 'Assigned',
                        'in_progress' => 'In Progress',
                        'blocked' => 'Blocked',
                        'review' => 'Review',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),
                SelectFilter::make('project')
                    ->relationship('project', 'name'),
                SelectFilter::make('sla')
                    ->label('SLA Status')
                    ->options([
                        'breached' => 'Breached',
                        'on_track' => 'On Track',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (($data['value'] ?? null) === 'breached') {
                            $query->whereHas('slaLog', fn ($q) => $q->where('response_breached', true)->orWhere('resolution_breached', true));
                        } elseif (($data['value'] ?? null) === 'on_track') {
                            $query->whereHas('slaLog', fn ($q) => $q->where('response_breached', false)->where('resolution_breached', false));
                        }
                    }),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (Task $record): bool => in_array($record->status, ['pending', 'assigned']))
                    ->action(function (Task $record): void {
                        $record->update(['status' => 'in_progress']);
                        Notification::make()
                            ->title('Task started')
                            ->success()
                            ->send();
                    }),
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->visible(fn (Task $record): bool => $record->status === 'in_progress')
                    ->action(function (Task $record): void {
                        $record->update(['status' => 'review']);
                        Notification::make()
                            ->title('Task submitted for review')
                            ->info()
                            ->send();
                    }),
                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Task $record): bool => in_array($record->status, ['in_progress', 'review']))
                    ->action(function (Task $record): void {
                        $record->update(['status' => 'completed']);
                        Notification::make()
                            ->title('Task marked as complete')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
            return $query->whereHas('project', function (Builder $pq) use ($user) {
                $pq->where('manager_id', $user->id)
                    ->orWhereHas('team', fn (Builder $tq) => $tq->where('manager_id', $user->id));
            });
        }

        if ($user->hasRole('team_leader')) {
            return $query->where(function (Builder $q) use ($user) {
                $q->whereHas('project.team', fn (Builder $tq) => $tq->where('team_leader_id', $user->id))
                    ->orWhereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $user->id));
            });
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $user->id))
                ->orWhereHas('project.members', fn (Builder $mq) => $mq->where('users.id', $user->id));
        });
    }

    public static function getRelations(): array
    {
        return [
            AssigneesRelationManager::class,
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
