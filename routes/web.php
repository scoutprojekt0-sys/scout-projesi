<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/scout-et.html', function () {
    return response(file_get_contents(base_path('scout-et.html')))
        ->header('Content-Type', 'text/html; charset=UTF-8');
});

Route::view('/ai-labeler.html', 'ai-labeler')->middleware('internal_tool');

Route::get('/{page}.html', function (string $page) {
    $path = base_path($page.'.html');

    abort_unless(is_file($path), 404);

    return response(file_get_contents($path))
        ->header('Content-Type', 'text/html; charset=UTF-8');
})->where('page', '[A-Za-z0-9_-]+');

Route::view('/auth/design-demo', 'auth-design-demo');
Route::view('/auth/sessions', 'auth-sessions');
Route::view('/app/core', 'core-product');
Route::view('/app/communication', 'communication-media');
