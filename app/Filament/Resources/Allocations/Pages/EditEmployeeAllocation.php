<?php

namespace App\Filament\Resources\Allocations\Pages;

use App\Filament\Resources\Allocations\EmployeeAllocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeAllocation extends EditRecord
{
    protected static string $resource = EmployeeAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
