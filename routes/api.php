<?php

use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\HomeButtonActionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('companies', CompanyController::class);
Route::post('update-company', [CompanyController::class, 'updateCompany']);

Route::get('calendar', [CalendarController::class, 'index']);
Route::get('company/search/{ticker}', [CompanyController::class, 'search']);
Route::get('company/search-ru/{ticker}', [CompanyController::class, 'searchRu']);

Route::post('home-button-action', [HomeButtonActionController::class, 'run']);
