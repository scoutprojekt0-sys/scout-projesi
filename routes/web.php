<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/index.html');
});

Route::view('/auth/design-demo', 'auth-design-demo');
Route::view('/auth/sessions', 'auth-sessions');
Route::view('/app/core', 'core-product');
Route::view('/app/communication', 'communication-media');
