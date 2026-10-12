<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::post('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/', function () {
    return view('welcome');
});
