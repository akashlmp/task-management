<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\EmployeeProjectAllocation;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectAllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static ?string $title = 'Allocated Employees & Capacity';

    public function form(Schema $schema): Schema
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

                        return "Current active load: {$active}%. Available remaining capacity: {$free}%.";
                    }),
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
                    ->label('Allocation Start')
                    ->default(now()),
                DatePicker::make('end_date')
                    ->label('Allocation End'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('role')
            ->headerActions([
                CreateAction::make()
                    ->label('Allocate Employee'),
            ])
            ->columns([
                TextColumn::make('employee.name')
                    ->label('Employee')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('employee.designation')
                    ->label('Designation')
                    ->placeholder('—'),
                TextColumn::make('role')
                    ->label('Assigned Role')
                    ->badge()
                    ->color('info'),
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
                    }),
                TextColumn::make('employee_total_load')
                    ->label('Employee Total Load')
                    ->getStateUsing(fn (EmployeeProjectAllocation $record): string => "{$record->employee?->active_allocation_percentage}%")
                    ->badge()
                    ->color(fn (string $state) => ((int) $state >= 80) ? 'danger' : 'gray'),
                TextColumn::make('start_date')
                    ->date(),
                TextColumn::make('end_date')
                    ->date()
                    ->placeholder('Ongoing'),
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
}
