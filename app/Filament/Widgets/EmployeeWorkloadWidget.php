<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class EmployeeWorkloadWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'manager']) || $user->can('users.view'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Employee Workload & Capacity Tracking')
            ->query(
                User::query()
                    ->where('status', 'active')
                    ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['employee', 'team_member', 'team_leader']))
            )
            ->columns([
                ImageColumn::make('profile_image')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?background=6366f1&color=fff&name=' . urlencode($record->name)),
                TextColumn::make('name')
                    ->label('Employee')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('designation')
                    ->label('Designation')
                    ->placeholder('—'),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->color('info')
                    ->placeholder('Unassigned'),
                TextColumn::make('active_allocation')
                    ->label('Workload Percentage')
                    ->getStateUsing(fn (User $record): string => "{$record->active_allocation_percentage}%")
                    ->badge()
                    ->color(function (string $state): string {
                        $load = (int) $state;
                        if ($load >= 80) {
                            return 'danger';
                        }
                        if ($load >= 50) {
                            return 'warning';
                        }

                        return 'success';
                    }),
                TextColumn::make('capacity_state')
                    ->label('Workload Status')
                    ->getStateUsing(function (User $record): string {
                        $load = $record->active_allocation_percentage;
                        $remaining = $record->remaining_allocation_percentage;

                        if ($load >= 100) {
                            return 'Max Capacity (0% free)';
                        }
                        if ($load >= 80) {
                            return "Heavy Load ({$remaining}% free)";
                        }
                        if ($load >= 40) {
                            return "Balanced ({$remaining}% free)";
                        }

                        return "Available ({$remaining}% free)";
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        if (str_contains($state, 'Max')) {
                            return 'danger';
                        }
                        if (str_contains($state, 'Heavy')) {
                            return 'warning';
                        }
                        if (str_contains($state, 'Balanced')) {
                            return 'info';
                        }

                        return 'success';
                    }),
                TextColumn::make('active_projects_count')
                    ->label('Active Projects')
                    ->getStateUsing(fn (User $record): int => $record->allocations()->whereHas('project', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))->count())
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated([5, 10, 25]);
    }
}
