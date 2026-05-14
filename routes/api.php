<?php

use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CompanyController;
use Illuminate\Support\Facades\Route;

Route::apiResource('companies', CompanyController::class);

Route::get('calendar', [CalendarController::class, 'index']);
