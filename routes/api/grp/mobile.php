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
use App\Actions\Api\Group\Auth\ResetMobilePassword;
use App\Actions\Api\Group\Auth\SendMobilePasswordResetCode;
use App\Actions\Api\Group\Auth\UpdateMobilePassword;
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
use App\Actions\Api\Group\GetMobileProfile;
use App\Actions\Api\Group\GetMobileWarehouses;
use App\Actions\HumanResources\ClockingMachine\ValidateClockingMachineQrCode;
use App\Actions\HumanResources\WorkSchedule\IndexShiftSchedules;
use App\Actions\Api\Group\Locations\AuditMobileLocation;
use App\Actions\Api\Group\Locations\GetMobileLocationsOverview;
use App\Actions\Api\Group\Locations\GetMobileStockMovementReasons;
use App\Actions\Api\Group\Locations\MoveAllMobileLocationOrgStocks;
use App\Actions\Api\Group\Locations\MoveSomeMobileLocationOrgStocks;
use App\Actions\Api\Group\Locations\RemoveMobileLocationOrgStocks;
use App\Actions\Api\Group\Locations\UpdateMobileLocationOrgStock;
use App\Actions\Api\Group\Locations\IndexMobileEmptyLocationOrgStocks;
use App\Actions\Api\Group\Locations\IndexMobileLocations;
use App\Actions\Api\Group\Locations\IndexMobileRemovedEmptyLocationOrgStocks;
use App\Actions\Api\Group\Locations\IndexMobileWarehouseAreas;
use App\Actions\Api\Group\Locations\ShowMobileLocation;
use App\Actions\Api\Group\Locations\UpdateMobileEmptyLocationsRemoval;
use App\Actions\SysAdmin\User\PasswordResetLink;
use App\Http\Middleware\VerifyMobileAppKey;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyMobileAppKey::class)->group(function () {
    Route::get('/ping', function () {
        return 'pong';
    })->name('ping');

    Route::post('/login', LoginMobileApp::class)->name('login')->middleware('throttle:mobile-login');
    Route::post('/password/reset-link', PasswordResetLink::class)->name('password.reset_link')->middleware('throttle:6,1');
    Route::post('/password/reset-code', SendMobilePasswordResetCode::class)->name('password.reset_code')->middleware('throttle:mobile-password-reset');
    Route::post('/password/reset', ResetMobilePassword::class)->name('password.reset')->middleware('throttle:mobile-password-reset');

    Route::middleware(['auth:sanctum', 'ability:mobile'])->group(function () {
        Route::post('/logout', LogoutMobileApp::class)->name('logout');
        Route::post('/password', UpdateMobilePassword::class)->name('password.update')->middleware('throttle:mobile-password-change');
        Route::get('/warehouses', GetMobileWarehouses::class)->name('warehouses');
        Route::get('/permissions', GetMobilePermissions::class)->name('permissions');
        Route::get('/profile', GetMobileProfile::class)->name('profile');

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
        Route::get('/warehouses/{warehouse:id}/locations-overview', GetMobileLocationsOverview::class)->name('warehouses.locations_overview');
        Route::get('/warehouses/{warehouse:id}/areas', IndexMobileWarehouseAreas::class)->name('warehouses.areas');
        Route::get('/warehouses/{warehouse:id}/locations', IndexMobileLocations::class)->name('warehouses.locations');
        Route::get('/warehouses/{warehouse:id}/empty-location-org-stocks', IndexMobileEmptyLocationOrgStocks::class)->name('warehouses.empty_location_org_stocks');
        Route::get('/warehouses/{warehouse:id}/removed-empty-location-org-stocks', IndexMobileRemovedEmptyLocationOrgStocks::class)->name('warehouses.removed_empty_location_org_stocks');
        Route::patch('/warehouses/{warehouse:id}/empty-locations-removal', UpdateMobileEmptyLocationsRemoval::class)->name('warehouses.empty_locations_removal.update');
        Route::get('/locations/{location:id}', ShowMobileLocation::class)->name('locations.show');
        Route::post('/locations/{location:id}/audit', AuditMobileLocation::class)->name('locations.audit');
        Route::post('/locations/{location:id}/move-all', MoveAllMobileLocationOrgStocks::class)->name('locations.move_all');
        Route::post('/locations/{location:id}/move-some', MoveSomeMobileLocationOrgStocks::class)->name('locations.move_some');
        Route::post('/locations/{location:id}/remove-org-stocks', RemoveMobileLocationOrgStocks::class)->name('locations.remove_org_stocks');
        Route::patch('/location-org-stocks/{locationOrgStock:id}', UpdateMobileLocationOrgStock::class)->name('location_org_stocks.update');
        Route::get('/stock-movement-reasons', GetMobileStockMovementReasons::class)->name('stock_movement_reasons');
    });
});
