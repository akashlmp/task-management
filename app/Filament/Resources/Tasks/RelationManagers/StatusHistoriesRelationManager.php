<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Status Audit Trail';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Action By')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('old_status')
                    ->label('Previous Status')
                    ->badge()
                    ->placeholder('Initiated')
                    ->colors([
                        'gray' => 'pending',
                        'info' => 'assigned',
                        'warning' => 'in_progress',
                        'danger' => 'blocked',
                        'primary' => 'review',
                        'success' => 'completed',
                    ]),
                TextColumn::make('new_status')
                    ->label('New Status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'info' => 'assigned',
                        'warning' => 'in_progress',
                        'danger' => 'blocked',
                        'primary' => 'review',
                        'success' => 'completed',
                    ]),
                TextColumn::make('comment')
                    ->label('Notes')
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Read-only audit log
            ])
            ->recordActions([
                // Read-only audit log
            ])
            ->toolbarActions([
                // Read-only audit log
            ]);
    }
}
