<?php

use App\Http\Controllers\FlightSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/flights/search', [FlightSearchController::class, 'search']);
