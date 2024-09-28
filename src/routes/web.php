<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PosterAppController;
use App\Http\Controllers\PosterApp\SyncCategoriesController;
use App\Http\Controllers\PosterApp\SyncProductsController;
use App\Http\Controllers\PosterWebhookController;
use App\Http\Middleware\EnsureCodeIsValid;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/poster-app/{code}', PosterAppController::class)
    ->middleware(EnsureCodeIsValid::class);

Route::post('/poster-app/{code}/sync-categories', SyncCategoriesController::class)
    ->middleware(EnsureCodeIsValid::class);

Route::post('/poster-app/{code}/sync-products', SyncProductsController::class)
    ->middleware(EnsureCodeIsValid::class);

Route::post('/poster-webhook', PosterWebhookController::class);
