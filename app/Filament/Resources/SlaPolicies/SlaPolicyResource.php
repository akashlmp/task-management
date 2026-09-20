<?php

namespace App\Filament\Resources\SlaPolicies;

use App\Filament\Resources\SlaPolicies\Pages\CreateSlaPolicy;
use App\Filament\Resources\SlaPolicies\Pages\EditSlaPolicy;
use App\Filament\Resources\SlaPolicies\Pages\ListSlaPolicies;
use App\Models\SlaPolicy;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SlaPolicyResource extends Resource
{
    protected static ?string $model = SlaPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'SLA & Performance';

    protected static ?string $navigationLabel = 'SLA Policies';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ])
                    ->required(),
                TextInput::make('response_time_minutes')
                    ->label('Response Time Limit')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('min')
                    ->helperText('Target time from task assignment to in-progress state')
                    ->required(),
                TextInput::make('resolution_time_minutes')
                    ->label('Resolution Time Limit')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('min')
                    ->helperText('Target time from task start to completion/review')
                    ->required(),
                TextInput::make('warning_percentage')
                    ->label('Warning Threshold')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(99)
                    ->default(80)
                    ->suffix('%')
                    ->helperText('Percentage of elapsed time before alerting assignee & manager')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active Policy')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'critical',
                    ])
                    ->sortable(),
                TextColumn::make('response_time_minutes')
                    ->label('Response Limit')
                    ->formatStateUsing(fn (int $state): string => SlaPolicy::formatMinutes($state) . " ({$state}m)")
                    ->sortable(),
                TextColumn::make('resolution_time_minutes')
                    ->label('Resolution Limit')
                    ->formatStateUsing(fn (int $state): string => SlaPolicy::formatMinutes($state) . " ({$state}m)")
                    ->sortable(),
                TextColumn::make('warning_percentage')
                    ->label('Warning At')
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlaPolicies::route('/'),
            'create' => CreateSlaPolicy::route('/create'),
            'edit' => EditSlaPolicy::route('/{record}/edit'),
        ];
    }
}
