<?php

/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-29m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use App\Actions\Api\Group\Auth\GetMobilePermissions;
use App\Actions\Api\Group\Auth\LoginMobileApp;
use App\Actions\Api\Group\Auth\LogoutMobileApp;
use App\Actions\Api\Group\GetMobileWarehouses;
use App\Actions\SysAdmin\User\PasswordResetLink;
use App\Http\Middleware\VerifyMobileAppKey;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyMobileAppKey::class)->group(function () {
    Route::get('/ping', function () {
        return 'pong';
    })->name('ping');

    Route::post('/login', LoginMobileApp::class)->name('login')->middleware('throttle:6,1');
    Route::post('/password/reset-link', PasswordResetLink::class)->name('password.reset_link')->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', 'ability:mobile'])->group(function () {
        Route::post('/logout', LogoutMobileApp::class)->name('logout');
        Route::get('/warehouses', GetMobileWarehouses::class)->name('warehouses');
        Route::get('/permissions', GetMobilePermissions::class)->name('permissions');
    });
});
