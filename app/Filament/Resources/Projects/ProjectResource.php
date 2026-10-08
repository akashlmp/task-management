<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\RelationManagers\ProjectAllocationsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ProjectTasksRelationManager;
use App\Models\Project;
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
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'manager']) || $user->can('projects.create'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Project Title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Project Code')
                    ->placeholder('e.g. PRJ-001')
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase;'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state)),
                TextInput::make('client_company')
                    ->label('Client / Organization')
                    ->placeholder('e.g. Acme Corporation')
                    ->maxLength(255),
                Select::make('project_manager_id')
                    ->label('Project Manager')
                    ->relationship('projectManager', 'name', modifyQueryUsing: fn (Builder $q) => $q->role(['manager', 'admin']))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('status')
                    ->options([
                        'planning' => 'Planning',
                        'in_progress' => 'In Progress',
                        'on_hold' => 'On Hold',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('planning')
                    ->required(),
                Select::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ])
                    ->default('medium')
                    ->required(),
                TextInput::make('budget')
                    ->label('Budget')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),
                TextInput::make('progress')
                    ->label('Progress (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->default(0)
                    ->helperText('Automatically updated based on task completions'),
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->default(now()),
                DatePicker::make('deadline')
                    ->label('Target Deadline'),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->nullable(),
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
                            fputcsv($handle, ['Code', 'Project Name', 'Client', 'Manager', 'Status', 'Priority', 'Budget', 'Progress', 'Start Date', 'Deadline']);

                            Project::with('projectManager')->chunk(100, function ($projects) use ($handle) {
                                foreach ($projects as $prj) {
                                    fputcsv($handle, [
                                        $prj->code,
                                        $prj->name,
                                        $prj->client_company ?? 'Internal',
                                        $prj->projectManager?->name ?? 'Unassigned',
                                        $prj->status,
                                        $prj->priority,
                                        $prj->budget,
                                        "{$prj->progress}%",
                                        $prj->start_date?->format('Y-m-d') ?? '',
                                        $prj->deadline?->format('Y-m-d') ?? '',
                                    ]);
                                }
                            });

                            fclose($handle);
                        }, 'projects-report-' . now()->format('Y-m-d') . '.csv', [
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
                    ->label('Project Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client_company')
                    ->label('Client')
                    ->placeholder('Internal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('projectManager.name')
                    ->label('Manager')
                    ->placeholder('Unassigned')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'planning',
                        'info' => 'in_progress',
                        'warning' => 'on_hold',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ]),
                TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge()
                    ->color(function (int $state): string {
                        if ($state >= 100) {
                            return 'success';
                        }
                        if ($state >= 50) {
                            return 'info';
                        }

                        return 'gray';
                    })
                    ->sortable(),
                TextColumn::make('budget')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->date()
                    ->sortable()
                    ->color(fn (Project $record) => ($record->deadline && $record->deadline < now()->startOfDay() && ! $record->isClosed()) ? 'danger' : null),
                TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->label('Tasks')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'planning' => 'Planning',
                        'in_progress' => 'In Progress',
                        'on_hold' => 'On Hold',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                SelectFilter::make('projectManager')
                    ->relationship('projectManager', 'name'),
            ])
            ->recordActions([
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
                                fputcsv($handle, ['Code', 'Project Name', 'Client', 'Manager', 'Status', 'Priority', 'Budget', 'Progress', 'Start Date', 'Deadline']);
                                foreach ($records as $prj) {
                                    fputcsv($handle, [
                                        $prj->code,
                                        $prj->name,
                                        $prj->client_company ?? 'Internal',
                                        $prj->projectManager?->name ?? 'Unassigned',
                                        $prj->status,
                                        $prj->priority,
                                        $prj->budget,
                                        "{$prj->progress}%",
                                        $prj->start_date?->format('Y-m-d') ?? '',
                                        $prj->deadline?->format('Y-m-d') ?? '',
                                    ]);
                                }
                                fclose($handle);
                            }, 'selected-projects-' . now()->format('Y-m-d') . '.csv', [
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

        // Employee Role: only view their assigned projects
        return $query->where(function (Builder $q) use ($user) {
            $q->where('project_manager_id', $user->id)
                ->orWhereHas('allocations', fn (Builder $aq) => $aq->where('employee_id', $user->id));
        });
    }

    public static function getRelations(): array
    {
        return [
            ProjectTasksRelationManager::class,
            ProjectAllocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
