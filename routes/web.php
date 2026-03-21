<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/index.html');
});

Route::get('/scout-et.html', function () {
    return response(file_get_contents(base_path('scout-et.html')))
        ->header('Content-Type', 'text/html; charset=UTF-8');
});

Route::view('/auth/design-demo', 'auth-design-demo');
Route::view('/auth/sessions', 'auth-sessions');
Route::view('/app/core', 'core-product');
Route::view('/app/communication', 'communication-media');
