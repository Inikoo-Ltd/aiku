<?php

/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-29m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use App\Actions\Api\Group\Auth\LoginMobileApp;
use App\Actions\Api\Group\Auth\LogoutMobileApp;
use App\Actions\Api\Group\GetApiProfile;
use App\Actions\Api\Group\GetMobileGroupDashboard;
use App\Actions\Api\Group\GetMobileNavigation;
use App\Actions\Api\Group\GetMobileOperationsDashboard;
use App\Actions\Api\Group\UpdateMobileAppTheme;
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
        Route::get('/profile', GetApiProfile::class)->name('profile');
        Route::patch('/profile/theme', UpdateMobileAppTheme::class)->name('profile.theme.update');
        Route::get('/navigation', GetMobileNavigation::class)->name('navigation');
        Route::get('/dashboard/group', GetMobileGroupDashboard::class)->name('dashboard.group');
        Route::get('/dashboard/operations', GetMobileOperationsDashboard::class)->name('dashboard.operations');
        Route::get('/dashboard/operations/{warehouse:slug}', [GetMobileOperationsDashboard::class, 'inWarehouse'])->name('dashboard.operations.warehouse');
    });
});
