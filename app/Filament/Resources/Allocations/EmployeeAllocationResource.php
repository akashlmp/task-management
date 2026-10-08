<?php

namespace App\Filament\Resources\Allocations;

use App\Filament\Resources\Allocations\Pages\CreateEmployeeAllocation;
use App\Filament\Resources\Allocations\Pages\EditEmployeeAllocation;
use App\Filament\Resources\Allocations\Pages\ListEmployeeAllocations;
use App\Models\EmployeeProjectAllocation;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeAllocationResource extends Resource
{
    protected static ?string $model = EmployeeProjectAllocation::class;

    protected static ?string $modelLabel = 'Workload Allocation';

    protected static ?string $pluralModelLabel = 'Employee Allocations';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'manager']) || $user->can('allocations.view'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->helperText(function (callable $get, ?EmployeeProjectAllocation $record): ?string {
                        $empId = $get('employee_id');
                        if (! $empId) {
                            return 'Select an employee to see current workload.';
                        }

                        $emp = User::find($empId);
                        if (! $emp) {
                            return null;
                        }

                        $active = $emp->active_allocation_percentage;
                        if ($record && $record->employee_id == $empId) {
                            $active -= $record->allocation_percentage;
                        }
                        $free = max(0, 100 - $active);

                        return "Employee current active load: {$active}%. Available remaining capacity: {$free}%.";
                    }),
                Select::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'name', modifyQueryUsing: fn (Builder $q) => $q->active())
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('allocation_percentage')
                    ->label('Allocation Percentage (%)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required()
                    ->rules([
                        fn (callable $get, ?EmployeeProjectAllocation $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $empId = $get('employee_id');
                            if ($empId) {
                                $emp = User::find($empId);
                                if ($emp) {
                                    $active = $emp->active_allocation_percentage;
                                    if ($record && $record->employee_id == $empId) {
                                        $active -= $record->allocation_percentage;
                                    }
                                    $total = $active + (int) $value;
                                    if ($total > 100) {
                                        $fail("Total active workload would be {$total}%, exceeding the 100% capacity limit.");
                                    }
                                }
                            }
                        },
                    ]),
                TextInput::make('role')
                    ->label('Role in Project')
                    ->default('Full-Stack Engineer')
                    ->required(),
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->default(now()),
                DatePicker::make('end_date')
                    ->label('End Date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')
                    ->label('Employee')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.designation')
                    ->label('Designation')
                    ->placeholder('—'),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('allocation_percentage')
                    ->label('Allocation %')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge()
                    ->color(function (int $state): string {
                        if ($state >= 70) {
                            return 'danger';
                        }
                        if ($state >= 40) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->sortable(),
                TextColumn::make('employee_active_load')
                    ->label('Employee Total Load')
                    ->getStateUsing(fn (EmployeeProjectAllocation $record): string => "{$record->employee?->active_allocation_percentage}%")
                    ->badge()
                    ->color(function (string $state): string {
                        $val = (int) $state;
                        if ($val >= 80) {
                            return 'danger';
                        }
                        if ($val >= 50) {
                            return 'warning';
                        }

                        return 'success';
                    }),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->placeholder('Ongoing')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('employee')
                    ->relationship('employee', 'name'),
                SelectFilter::make('project')
                    ->relationship('project', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeAllocations::route('/'),
            'create' => CreateEmployeeAllocation::route('/create'),
            'edit' => EditEmployeeAllocation::route('/{record}/edit'),
        ];
    }
}
