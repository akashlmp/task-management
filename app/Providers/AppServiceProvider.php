<?php

namespace App\Providers;

use App\Models\EmployeeProjectAllocation;
use App\Models\Project;
use App\Models\Task;
use App\Observers\EmployeeProjectAllocationObserver;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);
        Project::observe(ProjectObserver::class);
        EmployeeProjectAllocation::observe(EmployeeProjectAllocationObserver::class);
    }
}
