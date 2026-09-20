<?php

namespace App\Filament\Widgets;

use App\Models\SlaPolicy;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UrgentTasksTableWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Urgent & Overdue Tasks')
            ->query($this->getTableQuery())
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
                    ]),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'info' => 'assigned',
                        'warning' => 'in_progress',
                        'danger' => 'blocked',
                        'primary' => 'review',
                        'success' => 'completed',
                    ]),
                TextColumn::make('assignees.name')
                    ->label('Assignees')
                    ->badge()
                    ->separator(','),
                TextColumn::make('due_at')
                    ->label('Target Due Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sla_status')
                    ->label('SLA Status')
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
            ])
            ->actions([
                Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (Task $record): bool => in_array($record->status, ['pending', 'assigned']))
                    ->action(function (Task $record): void {
                        $record->update(['status' => 'in_progress']);
                        Notification::make()->title('Task started')->success()->send();
                    }),
                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Task $record): bool => in_array($record->status, ['in_progress', 'review']))
                    ->action(function (Task $record): void {
                        $record->update(['status' => 'completed']);
                        Notification::make()->title('Task completed')->success()->send();
                    }),
            ])
            ->paginated([5, 10]);
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        $query = Task::query()
            ->with(['project', 'slaLog', 'assignees'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function (Builder $q) {
                $q->where(function (Builder $sub) {
                    $sub->whereNotNull('due_at')->where('due_at', '<', now());
                })->orWhere('priority', 'critical');
            })
            ->orderByRaw("CASE WHEN due_at IS NOT NULL AND due_at < NOW() THEN 0 ELSE 1 END")
            ->orderBy('due_at');

        if ($user && ! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->whereHas('project', function (Builder $pq) use ($user) {
                    $pq->where('manager_id', $user->id)
                        ->orWhereHas('team', fn (Builder $tq) => $tq->where('manager_id', $user->id));
                });
            } elseif ($user->hasRole('team_leader')) {
                $query->where(function (Builder $q) use ($user) {
                    $q->whereHas('project.team', fn (Builder $tq) => $tq->where('team_leader_id', $user->id))
                        ->orWhereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $user->id));
                });
            } else {
                $query->where(function (Builder $q) use ($user) {
                    $q->whereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $user->id))
                        ->orWhereHas('project.members', fn (Builder $mq) => $mq->where('users.id', $user->id));
                });
            }
        }

        return $query;
    }
}
