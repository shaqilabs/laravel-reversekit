<?php

use Illuminate\Support\Facades\Route;

// ReverseKit Generated Routes (2024-01-01 00:00:00)
// Entities: User, Post

Route::apiResource('users', \App\Http\Controllers\UserController::class);
Route::apiResource('posts', \App\Http\Controllers\PostController::class);
