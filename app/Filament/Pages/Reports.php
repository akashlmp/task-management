<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Analytics & Reports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports';

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public ?int $selectedTeamId = null;

    public ?int $selectedProjectId = null;

    protected ?Collection $cachedTeamMetrics = null;

    protected ?Collection $cachedMemberMetrics = null;

    protected ?Collection $cachedProjectMetrics = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->can('reports.view')
            || $user->can('performance.view');
    }

    public function mount(): void
    {
        $this->fromDate = now()->subDays(30)->toDateString();
        $this->toDate = now()->toDateString();
        $this->loadDefaultActiveTab();
    }

    public function getTabs(): array
    {
        return [
            'teams' => Tab::make('Team Performance')
                ->icon('heroicon-m-user-group'),
            'members' => Tab::make('Member Workload')
                ->icon('heroicon-m-users'),
            'projects' => Tab::make('Project Health')
                ->icon('heroicon-m-clipboard-document-check'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'teams';
    }

    public function updatedActiveTab(): void
    {
        $this->cachedTeamMetrics = null;
        $this->cachedMemberMetrics = null;
        $this->cachedProjectMetrics = null;
        $this->resetTable();
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['teams', 'members', 'projects'])) {
            $this->activeTab = $tab;
            $this->updatedActiveTab();
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'members' => $this->memberWorkloadTable($table),
            'projects' => $this->projectHealthTable($table),
            default => $this->teamPerformanceTable($table),
        };
    }

    protected function teamPerformanceTable(Table $table): Table
    {
        $user = auth()->user();
        $query = Team::query()->where('status', 'active')->with(['manager', 'leader', 'projects']);

        if (! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->where('manager_id', $user->id);
            } elseif ($user->hasRole('team_leader')) {
                $query->where('team_leader_id', $user->id);
            } else {
                $query->whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id));
            }
        }

        return $table
            ->query($query)
            ->heading('Team Output & SLA Performance')
            ->description('Operational productivity, resolution averages, and SLA adherence across active teams')
            ->columns([
                TextColumn::make('name')
                    ->label('Team Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('manager.name')
                    ->label('Manager')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('leader.name')
                    ->label('Team Leader')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_tasks')
                    ->label('Tasks Created')
                    ->alignCenter()
                    ->getStateUsing(fn (Team $record) => $this->getTeamMetricsMap()->get($record->id)['total_tasks'] ?? 0),
                TextColumn::make('completed_tasks')
                    ->label('Completed')
                    ->alignCenter()
                    ->color('success')
                    ->weight('bold')
                    ->getStateUsing(fn (Team $record) => $this->getTeamMetricsMap()->get($record->id)['completed_tasks'] ?? 0),
                TextColumn::make('completion_rate')
                    ->label('Completion Rate')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Team $record): string => ($this->getTeamMetricsMap()->get($record->id)['completion_rate'] ?? 0) . '%')
                    ->color(function (string $state): string {
                        $val = (float) rtrim($state, '%');
                        return match (true) {
                            $val >= 80 => 'success',
                            $val >= 50 => 'warning',
                            default => 'danger',
                        };
                    }),
                TextColumn::make('sla_compliance_rate')
                    ->label('SLA Compliance')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Team $record): string => ($this->getTeamMetricsMap()->get($record->id)['sla_compliance_rate'] ?? 100) . '%')
                    ->description(fn (Team $record): string =>
                        (($metric = $this->getTeamMetricsMap()->get($record->id))
                            ? "{$metric['sla_compliant']} met / {$metric['sla_breached']} breached"
                            : '0 met / 0 breached')
                    )
                    ->color(function (string $state): string {
                        $val = (float) rtrim($state, '%');
                        return $val >= 90 ? 'success' : 'danger';
                    }),
                TextColumn::make('avg_resolution_hours')
                    ->label('Avg Resolution')
                    ->alignCenter()
                    ->getStateUsing(fn (Team $record): string => ($this->getTeamMetricsMap()->get($record->id)['avg_resolution_hours'] ?? 0) . ' hrs'),
                TextColumn::make('total_logged_hours')
                    ->label('Work Logged')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('primary')
                    ->getStateUsing(fn (Team $record): string => ($this->getTeamMetricsMap()->get($record->id)['total_logged_hours'] ?? 0) . ' hrs'),
            ])
            ->filters([
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from_date')
                            ->label('From Date')
                            ->default(now()->subDays(30)->toDateString()),
                        DatePicker::make('to_date')
                            ->label('To Date')
                            ->default(now()->toDateString()),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['from_date'])) {
                            $indicators[] = 'From: ' . Carbon::parse($data['from_date'])->toFormattedDateString();
                        }
                        if (! empty($data['to_date'])) {
                            $indicators[] = 'To: ' . Carbon::parse($data['to_date'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
                SelectFilter::make('team_id')
                    ->label('Filter Team')
                    ->options(fn () => $this->getTeamsProperty()->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->where('id', $data['value']);
                        }
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(fn () => $this->exportCurrentReport()),
            ]);
    }

    protected function memberWorkloadTable(Table $table): Table
    {
        $user = auth()->user();
        $query = User::query()->where('status', 'active')->with(['teams']);

        if (! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->whereHas('teams', fn (Builder $tq) => $tq->where('manager_id', $user->id));
            } elseif ($user->hasRole('team_leader')) {
                $query->whereHas('teams', fn (Builder $tq) => $tq->where('team_leader_id', $user->id));
            } else {
                $query->where('id', $user->id);
            }
        }

        return $table
            ->query($query)
            ->heading('Member Workload & Delivery Metrics')
            ->description('Individual task throughput, on-time completion rates, and logged hours')
            ->columns([
                TextColumn::make('name')
                    ->label('Member Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record): string => ($record->employee_code ?? '-') . ' • ' . $record->email),
                TextColumn::make('teams.name')
                    ->label('Team')
                    ->badge()
                    ->placeholder('Unassigned'),
                TextColumn::make('total_assigned')
                    ->label('Assigned')
                    ->alignCenter()
                    ->weight('bold')
                    ->getStateUsing(fn (User $record) => $this->getMemberMetricsMap()->get($record->id)['total_assigned'] ?? 0),
                TextColumn::make('completed_tasks')
                    ->label('Completed')
                    ->alignCenter()
                    ->color('success')
                    ->weight('bold')
                    ->getStateUsing(fn (User $record) => $this->getMemberMetricsMap()->get($record->id)['completed_tasks'] ?? 0),
                TextColumn::make('completion_rate')
                    ->label('Completion %')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (User $record): string => ($this->getMemberMetricsMap()->get($record->id)['completion_rate'] ?? 0) . '%')
                    ->color(function (string $state): string {
                        $val = (float) rtrim($state, '%');
                        return match (true) {
                            $val >= 80 => 'success',
                            $val >= 50 => 'warning',
                            default => 'danger',
                        };
                    }),
                TextColumn::make('on_time_rate')
                    ->label('On-Time %')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (User $record): string => ($this->getMemberMetricsMap()->get($record->id)['on_time_rate'] ?? 100) . '%')
                    ->color(function (string $state): string {
                        $val = (float) rtrim($state, '%');
                        return $val >= 90 ? 'success' : 'warning';
                    }),
                TextColumn::make('sla_breaches')
                    ->label('SLA Breaches')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (User $record) => $this->getMemberMetricsMap()->get($record->id)['sla_breaches'] ?? 0)
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('logged_hours')
                    ->label('Work Logged')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('primary')
                    ->getStateUsing(fn (User $record): string => ($this->getMemberMetricsMap()->get($record->id)['logged_hours'] ?? 0) . ' hrs'),
            ])
            ->filters([
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from_date')
                            ->label('From Date')
                            ->default(now()->subDays(30)->toDateString()),
                        DatePicker::make('to_date')
                            ->label('To Date')
                            ->default(now()->toDateString()),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['from_date'])) {
                            $indicators[] = 'From: ' . Carbon::parse($data['from_date'])->toFormattedDateString();
                        }
                        if (! empty($data['to_date'])) {
                            $indicators[] = 'To: ' . Carbon::parse($data['to_date'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
                SelectFilter::make('team_id')
                    ->label('Filter Team')
                    ->options(fn () => $this->getTeamsProperty()->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('teams', fn ($q) => $q->where('teams.id', $data['value']));
                        }
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(fn () => $this->exportCurrentReport()),
            ]);
    }

    protected function projectHealthTable(Table $table): Table
    {
        $user = auth()->user();
        $query = Project::query()->with(['team', 'manager']);

        if (! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->where(function (Builder $q) use ($user) {
                    $q->where('manager_id', $user->id)
                        ->orWhereHas('team', fn (Builder $tq) => $tq->where('manager_id', $user->id));
                });
            } elseif ($user->hasRole('team_leader')) {
                $query->whereHas('team', fn (Builder $tq) => $tq->where('team_leader_id', $user->id));
            } else {
                $query->whereHas('members', fn (Builder $mq) => $mq->where('users.id', $user->id));
            }
        }

        return $table
            ->query($query)
            ->heading('Project Health & Progress Tracking')
            ->description('Milestone status, task resolution, overdue items, and SLA adherence across active projects')
            ->columns([
                TextColumn::make('name')
                    ->label('Project Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Project $record): string => $record->code),
                TextColumn::make('team.name')
                    ->label('Team')
                    ->placeholder('General')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('manager.name')
                    ->label('Manager')
                    ->placeholder('Unassigned')
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
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                TextColumn::make('total_tasks')
                    ->label('Tasks')
                    ->alignCenter()
                    ->weight('bold')
                    ->getStateUsing(fn (Project $record) => $this->getProjectMetricsMap()->get($record->id)['total_tasks'] ?? 0)
                    ->description(fn (Project $record): string =>
                        (($metric = $this->getProjectMetricsMap()->get($record->id))
                            ? "{$metric['completed_tasks']} done, {$metric['in_progress_tasks']} active"
                            : '0 done, 0 active')
                    ),
                TextColumn::make('overdue_tasks')
                    ->label('Overdue')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Project $record) => $this->getProjectMetricsMap()->get($record->id)['overdue_tasks'] ?? 0)
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'success'),
                TextColumn::make('progress_percent')
                    ->label('Progress')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Project $record): string => ($this->getProjectMetricsMap()->get($record->id)['progress_percent'] ?? 0) . '%')
                    ->color(function (string $state): string {
                        $val = (float) rtrim($state, '%');
                        return match (true) {
                            $val >= 80 => 'success',
                            $val >= 40 => 'info',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('sla_compliance_rate')
                    ->label('SLA Compliance')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Project $record): string => ($this->getProjectMetricsMap()->get($record->id)['sla_compliance_rate'] ?? 100) . '%')
                    ->color(function (string $state): string {
                        $val = (float) rtrim($state, '%');
                        return $val >= 90 ? 'success' : 'danger';
                    }),
            ])
            ->filters([
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from_date')
                            ->label('From Date')
                            ->default(now()->subDays(30)->toDateString()),
                        DatePicker::make('to_date')
                            ->label('To Date')
                            ->default(now()->toDateString()),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['from_date'])) {
                            $indicators[] = 'From: ' . Carbon::parse($data['from_date'])->toFormattedDateString();
                        }
                        if (! empty($data['to_date'])) {
                            $indicators[] = 'To: ' . Carbon::parse($data['to_date'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
                SelectFilter::make('team_id')
                    ->label('Filter Team')
                    ->options(fn () => $this->getTeamsProperty()->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->where('team_id', $data['value']);
                        }
                    }),
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'planning' => 'Planning',
                        'active' => 'Active',
                        'on_hold' => 'On Hold',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(fn () => $this->exportCurrentReport()),
            ]);
    }

    public function getDateRangeFilterValues(): array
    {
        $filterData = $this->tableFilters['date_range'] ?? [];

        return [
            'from' => $filterData['from_date'] ?? $this->fromDate ?? now()->subDays(30)->toDateString(),
            'to' => $filterData['to_date'] ?? $this->toDate ?? now()->toDateString(),
        ];
    }

    protected function getTeamMetricsMap(): Collection
    {
        if ($this->cachedTeamMetrics !== null) {
            return $this->cachedTeamMetrics;
        }

        $dates = $this->getDateRangeFilterValues();
        $selectedTeamId = $this->tableFilters['team_id']['value'] ?? $this->selectedTeamId ?? null;

        /** @var ReportingService $service */
        $service = app(ReportingService::class);

        return $this->cachedTeamMetrics = $service->getTeamPerformance(
            user: auth()->user(),
            fromDate: $dates['from'],
            toDate: $dates['to'],
            teamId: $selectedTeamId ? (int) $selectedTeamId : null,
            projectId: $this->selectedProjectId ? (int) $this->selectedProjectId : null
        )->keyBy('team_id');
    }

    protected function getMemberMetricsMap(): Collection
    {
        if ($this->cachedMemberMetrics !== null) {
            return $this->cachedMemberMetrics;
        }

        $dates = $this->getDateRangeFilterValues();
        $selectedTeamId = $this->tableFilters['team_id']['value'] ?? $this->selectedTeamId ?? null;

        /** @var ReportingService $service */
        $service = app(ReportingService::class);

        return $this->cachedMemberMetrics = $service->getMemberPerformance(
            user: auth()->user(),
            fromDate: $dates['from'],
            toDate: $dates['to'],
            teamId: $selectedTeamId ? (int) $selectedTeamId : null
        )->keyBy('user_id');
    }

    protected function getProjectMetricsMap(): Collection
    {
        if ($this->cachedProjectMetrics !== null) {
            return $this->cachedProjectMetrics;
        }

        $dates = $this->getDateRangeFilterValues();
        $selectedTeamId = $this->tableFilters['team_id']['value'] ?? $this->selectedTeamId ?? null;

        /** @var ReportingService $service */
        $service = app(ReportingService::class);

        return $this->cachedProjectMetrics = $service->getProjectHealth(
            user: auth()->user(),
            fromDate: $dates['from'],
            toDate: $dates['to'],
            teamId: $selectedTeamId ? (int) $selectedTeamId : null,
            projectId: $this->selectedProjectId ? (int) $this->selectedProjectId : null
        )->keyBy('project_id');
    }

    public function getTeamsProperty()
    {
        $user = auth()->user();
        $query = Team::query()->where('status', 'active');

        if (! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->where('manager_id', $user->id);
            } elseif ($user->hasRole('team_leader')) {
                $query->where('team_leader_id', $user->id);
            } else {
                $query->whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id));
            }
        }

        return $query->orderBy('name')->get();
    }

    public function getProjectsProperty()
    {
        $user = auth()->user();
        $query = Project::query();

        if (! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->where('manager_id', $user->id)
                    ->orWhereHas('team', fn (Builder $tq) => $tq->where('manager_id', $user->id));
            } elseif ($user->hasRole('team_leader')) {
                $query->whereHas('team', fn (Builder $tq) => $tq->where('team_leader_id', $user->id));
            } else {
                $query->whereHas('members', fn (Builder $mq) => $mq->where('users.id', $user->id));
            }
        }

        if ($this->selectedTeamId) {
            $query->where('team_id', $this->selectedTeamId);
        }

        return $query->orderBy('name')->get();
    }

    public function getTeamMetricsProperty()
    {
        $dates = $this->getDateRangeFilterValues();
        $selectedTeamId = $this->tableFilters['team_id']['value'] ?? $this->selectedTeamId ?? null;

        /** @var ReportingService $service */
        $service = app(ReportingService::class);

        return $service->getTeamPerformance(
            user: auth()->user(),
            fromDate: $dates['from'],
            toDate: $dates['to'],
            teamId: $selectedTeamId ? (int) $selectedTeamId : null,
            projectId: $this->selectedProjectId ? (int) $this->selectedProjectId : null
        );
    }

    public function getMemberMetricsProperty()
    {
        $dates = $this->getDateRangeFilterValues();
        $selectedTeamId = $this->tableFilters['team_id']['value'] ?? $this->selectedTeamId ?? null;

        /** @var ReportingService $service */
        $service = app(ReportingService::class);

        return $service->getMemberPerformance(
            user: auth()->user(),
            fromDate: $dates['from'],
            toDate: $dates['to'],
            teamId: $selectedTeamId ? (int) $selectedTeamId : null
        );
    }

    public function getProjectMetricsProperty()
    {
        $dates = $this->getDateRangeFilterValues();
        $selectedTeamId = $this->tableFilters['team_id']['value'] ?? $this->selectedTeamId ?? null;

        /** @var ReportingService $service */
        $service = app(ReportingService::class);

        return $service->getProjectHealth(
            user: auth()->user(),
            fromDate: $dates['from'],
            toDate: $dates['to'],
            teamId: $selectedTeamId ? (int) $selectedTeamId : null,
            projectId: $this->selectedProjectId ? (int) $this->selectedProjectId : null
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function exportCurrentReport(): StreamedResponse
    {
        /** @var ReportingService $service */
        $service = app(ReportingService::class);
        $tab = $this->activeTab;
        $dates = $this->getDateRangeFilterValues();
        $selectedTeamId = $this->tableFilters['team_id']['value'] ?? $this->selectedTeamId ?? null;

        if ($tab === 'members') {
            $data = $service->getMemberPerformance(
                user: auth()->user(),
                fromDate: $dates['from'],
                toDate: $dates['to'],
                teamId: $selectedTeamId ? (int) $selectedTeamId : null
            );
            $headers = ['User ID', 'Name', 'Employee Code', 'Email', 'Team', 'Total Tasks', 'Completed', 'Completion %', 'On-Time %', 'SLA Breaches', 'Logged Hours'];
            $filename = 'member-performance-' . now()->format('Y-m-d') . '.csv';
        } elseif ($tab === 'projects') {
            $data = $service->getProjectHealth(
                user: auth()->user(),
                fromDate: $dates['from'],
                toDate: $dates['to'],
                teamId: $selectedTeamId ? (int) $selectedTeamId : null,
                projectId: $this->selectedProjectId ? (int) $this->selectedProjectId : null
            );
            $headers = ['Project ID', 'Project Name', 'Code', 'Team', 'Manager', 'Status', 'Priority', 'Total Tasks', 'Completed', 'In Progress', 'Overdue', 'Progress %', 'SLA Compliance %'];
            $filename = 'project-health-' . now()->format('Y-m-d') . '.csv';
        } else {
            $data = $service->getTeamPerformance(
                user: auth()->user(),
                fromDate: $dates['from'],
                toDate: $dates['to'],
                teamId: $selectedTeamId ? (int) $selectedTeamId : null,
                projectId: $this->selectedProjectId ? (int) $this->selectedProjectId : null
            );
            $headers = ['Team ID', 'Team Name', 'Manager', 'Leader', 'Total Tasks', 'Completed', 'Completion %', 'SLA Compliant', 'SLA Breached', 'SLA Compliance %', 'Avg Resolution (Hrs)', 'Total Logged (Hrs)'];
            $filename = 'team-performance-' . now()->format('Y-m-d') . '.csv';
        }

        $csvContent = $service->generateCsv($headers, $data);

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
