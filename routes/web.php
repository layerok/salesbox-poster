<?php


use Illuminate\Support\Facades\Route;

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

Route::get('/poster-app/{code}', \App\Http\Controllers\PosterAppController::class);

Route::post('/poster-app/{code}/sync-categories', \App\Http\Controllers\PosterApp\SyncCategoriesController::class);
