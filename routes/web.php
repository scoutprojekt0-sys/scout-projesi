<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/auth/design-demo', 'auth-design-demo');
Route::view('/auth/sessions', 'auth-sessions');
Route::view('/app/core', 'core-product');
Route::view('/app/communication', 'communication-media');
