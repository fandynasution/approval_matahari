<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


use App\Http\Controllers\StaffActionController as StaffAction;
Route::POST('/staffaction_por', [StaffAction::class, 'staffaction_por']);