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
use App\Actions\Api\Group\Clocking\CancelMobileAttendanceAdjustment;
use App\Actions\Api\Group\Clocking\DownloadMobileClockingAttachment;
use App\Actions\Api\Group\Clocking\GetMobileAttendanceAdjustments;
use App\Actions\Api\Group\Clocking\GetMobileClocking;
use App\Actions\Api\Group\Clocking\GetMobileClockingCalendar;
use App\Actions\Api\Group\Clocking\GetMobileLeaves;
use App\Actions\Api\Group\Clocking\GetMobileOvertimeRequests;
use App\Actions\Api\Group\Clocking\GetMobileTimesheets;
use App\Actions\Api\Group\Clocking\StoreMobileAttendanceAdjustment;
use App\Actions\Api\Group\Clocking\StoreMobileLeave;
use App\Actions\Api\Group\Clocking\StoreMobileOvertimeRequest;
use App\Actions\Api\Group\Clocking\UpdateMobileClockingNotes;
use App\Actions\Api\Group\Clocking\UpdateMobileLeave;
use App\Actions\Api\Group\Clocking\UpdateMobileOvertimeRequest;
use App\Actions\Api\Group\GetMobileWarehouses;
use App\Actions\HumanResources\ClockingMachine\ValidateClockingMachineQrCode;
use App\Actions\HumanResources\WorkSchedule\IndexShiftSchedules;
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

        Route::prefix('clocking')->name('clocking.')->group(function () {
            Route::get('/', GetMobileClocking::class)->name('show');
            Route::get('/timesheets', GetMobileTimesheets::class)->name('timesheets');
            Route::get('/shifts', IndexShiftSchedules::class)->name('shifts');
            Route::post('/qr', ValidateClockingMachineQrCode::class)->name('qr');
            Route::patch('/{clocking:id}/notes', UpdateMobileClockingNotes::class)->name('notes');

            Route::get('/leaves', GetMobileLeaves::class)->name('leaves.index');
            Route::post('/leaves', StoreMobileLeave::class)->name('leaves.store');
            Route::post('/leaves/{leave:id}', UpdateMobileLeave::class)->name('leaves.update');

            Route::get('/adjustments', GetMobileAttendanceAdjustments::class)->name('adjustments.index');
            Route::post('/adjustments', StoreMobileAttendanceAdjustment::class)->name('adjustments.store');
            Route::delete('/adjustments/{adjustment:id}', CancelMobileAttendanceAdjustment::class)->name('adjustments.cancel');

            Route::get('/overtime-requests', GetMobileOvertimeRequests::class)->name('overtime_requests.index');
            Route::post('/overtime-requests', StoreMobileOvertimeRequest::class)->name('overtime_requests.store');
            Route::patch('/overtime-requests/{overtimeRequest:id}', UpdateMobileOvertimeRequest::class)->name('overtime_requests.update');

            Route::get('/calendar', GetMobileClockingCalendar::class)->name('calendar');
            Route::get('/attachments/{media:ulid}', DownloadMobileClockingAttachment::class)->name('attachments.download');
        });
    });
});
