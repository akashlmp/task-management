<?php

use App\Http\Controllers\ReportPrintController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin')->name('home');

Route::get('/admin/reports/printable-summary', [ReportPrintController::class, 'show'])
    ->name('reports.printable')
    ->middleware(['web', 'auth']);
