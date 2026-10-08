<?php

namespace App\Filament\Resources\Allocations\Pages;

use App\Filament\Resources\Allocations\EmployeeAllocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeAllocations extends ListRecords
{
    protected static string $resource = EmployeeAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Allocation'),
        ];
    }
}
