<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\RelationManagers\AssignedProjectsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\AssignedTasksRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Employee';

    protected static ?string $pluralModelLabel = 'Employees';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'manager']) || $user->can('users.view'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('profile_image')
                    ->label('Profile Photo')
                    ->avatar()
                    ->image()
                    ->directory('employees/avatars')
                    ->maxSize(2048)
                    ->columnSpanFull(),
                TextInput::make('employee_code')
                    ->label('Employee Code')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Auto-generated (e.g. EMP-001)'),
                TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
                TextInput::make('designation')
                    ->label('Job Designation')
                    ->placeholder('e.g. Senior Software Engineer')
                    ->maxLength(255),
                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('phone')
                    ->label('Phone Number')
                    ->tel()
                    ->maxLength(50),
                DatePicker::make('joining_date')
                    ->label('Joining Date')
                    ->default(now()),
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->preload()
                    ->multiple()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'manager'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile_image')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?background=6366f1&color=fff&name=' . urlencode($record->name)),
                TextColumn::make('employee_code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('designation')
                    ->label('Designation')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->placeholder('Unassigned'),
                TextColumn::make('active_workload')
                    ->label('Workload')
                    ->getStateUsing(fn (User $record): string => "{$record->active_allocation_percentage}%")
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
                    })
                    ->description(fn (User $record): string => "Cap: {$record->remaining_allocation_percentage}% free"),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ]),
                TextColumn::make('joining_date')
                    ->label('Joined')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->relationship('department', 'name'),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
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

    public static function getRelations(): array
    {
        return [
            AssignedProjectsRelationManager::class,
            AssignedTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
