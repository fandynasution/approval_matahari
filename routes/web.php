<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\EnvController;

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

Route::get('/env', [EnvController::class, 'index']);

use App\Http\Controllers\DatabaseConnectionController;

Route::get('/check-database', [DatabaseConnectionController::class, 'checkConnection']);
