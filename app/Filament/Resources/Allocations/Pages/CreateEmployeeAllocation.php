<?php

namespace App\Filament\Resources\Allocations\Pages;

use App\Filament\Resources\Allocations\EmployeeAllocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeAllocation extends CreateRecord
{
    protected static string $resource = EmployeeAllocationResource::class;
}
