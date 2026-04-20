<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mobile', function () {
    return response()->file(base_path('mobile/index.html'));
});

Route::get('/mobile/app.js', function () {
    return response()->file(base_path('mobile/app.js'), ['Content-Type' => 'application/javascript']);
});
