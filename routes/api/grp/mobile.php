<?php

/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-29m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use App\Actions\Api\Group\Auth\GetMobilePermissions;
use App\Actions\Api\Group\Chat\IndexMobileStaffMessages;
use App\Actions\Api\Group\Chat\OpenMobileDeliveryNoteChat;
use App\Actions\Api\Group\Chat\OpenMobilePickingSessionChat;
use App\Actions\Api\Group\Chat\SendMobileStaffMessage;
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
use App\Actions\Api\Group\GoodsOut\AddMobilePickingSessionDeliveryNotes;
use App\Actions\Api\Group\GoodsOut\AttachMobilePickingSessionTrolley;
use App\Actions\Api\Group\GoodsOut\AutoFinishMobileWaitingDeliveryNote;
use App\Actions\Api\Group\GoodsOut\CancelMobileDeliveryNote;
use App\Actions\Api\Group\GoodsOut\ChangeMobileDeliveryNotePickedBay;
use App\Actions\Api\Group\GoodsOut\ChangeMobileDeliveryNoteTrolley;
use App\Actions\Api\Group\GoodsOut\DeleteMobilePicking;
use App\Actions\Api\Group\GoodsOut\DeleteMobileShipment;
use App\Actions\Api\Group\GoodsOut\DetachMobilePickingSessionTrolley;
use App\Actions\Api\Group\GoodsOut\DispatchMobileDeliveryNote;
use App\Actions\Api\Group\GoodsOut\DownloadMobileDeliveryNoteInsertPdf;
use App\Actions\Api\Group\GoodsOut\DownloadMobileDeliveryNotePdf;
use App\Actions\Api\Group\GoodsOut\DownloadMobileGiftMessagePdf;
use App\Actions\Api\Group\GoodsOut\DownloadMobilePackingListPdf;
use App\Actions\Api\Group\GoodsOut\DownloadMobileShipmentLabel;
use App\Actions\Api\Group\GoodsOut\FinaliseAndDispatchMobileDeliveryNote;
use App\Actions\Api\Group\GoodsOut\FinaliseMobileDeliveryNote;
use App\Actions\Api\Group\GoodsOut\GetMobileDeliveryNote;
use App\Actions\Api\Group\GoodsOut\GetMobileDeliveryNoteCounts;
use App\Actions\Api\Group\GoodsOut\GetMobileDeliveryNotes;
use App\Actions\Api\Group\GoodsOut\GetMobilePickingSession;
use App\Actions\Api\Group\GoodsOut\GetMobileScan;
use App\Actions\Api\Group\GoodsOut\IndexMobileDispatchPersonnel;
use App\Actions\Api\Group\GoodsOut\IndexMobilePickedBays;
use App\Actions\Api\Group\GoodsOut\IndexMobilePickers;
use App\Actions\Api\Group\GoodsOut\IndexMobilePickingSessions;
use App\Actions\Api\Group\GoodsOut\IndexMobileShippers;
use App\Actions\Api\Group\GoodsOut\IndexMobileTrolleys;
use App\Actions\Api\Group\GoodsOut\PackMobileDeliveryNoteItem;
use App\Actions\Api\Group\GoodsOut\PackMobileDeliveryNoteItemByScan;
use App\Actions\Api\Group\GoodsOut\PackMobilePickingSessionItemByScan;
use App\Actions\Api\Group\GoodsOut\PickAllMobileDeliveryNoteItem;
use App\Actions\Api\Group\GoodsOut\PickMobileDeliveryNoteItem;
use App\Actions\Api\Group\GoodsOut\PickMobileDeliveryNoteItemByScan;
use App\Actions\Api\Group\GoodsOut\PickMobilePickingSessionItemByScan;
use App\Actions\Api\Group\GoodsOut\PrintMobileDeliveryNoteInserts;
use App\Actions\Api\Group\GoodsOut\PrintMobileShipmentLabel;
use App\Actions\Api\Group\GoodsOut\PutBackMobileIncompleteSetParts;
use App\Actions\Api\Group\GoodsOut\RemoveMobilePickingSessionDeliveryNotes;
use App\Actions\Api\Group\GoodsOut\RetryMobileShipmentWithAddress;
use App\Actions\Api\Group\GoodsOut\SetMobileDeliveryNoteAsPacked;
use App\Actions\Api\Group\GoodsOut\SetMobileDeliveryNoteAsPicked;
use App\Actions\Api\Group\GoodsOut\SetMobileDeliveryNoteItemAsWaiting;
use App\Actions\Api\Group\GoodsOut\StartMobilePacking;
use App\Actions\Api\Group\GoodsOut\StartMobilePicking;
use App\Actions\Api\Group\GoodsOut\StartMobilePickingSession;
use App\Actions\Api\Group\GoodsOut\StoreMobileExternalShipment;
use App\Actions\Api\Group\GoodsOut\StoreMobileNotPickedPicking;
use App\Actions\Api\Group\GoodsOut\StoreMobilePickingSession;
use App\Actions\Api\Group\GoodsOut\StoreMobileShipment;
use App\Actions\Api\Group\GoodsOut\TakeOverMobileDeliveryNote;
use App\Actions\Api\Group\GoodsOut\UndoMobilePackingDeliveryNote;
use App\Actions\Api\Group\GoodsOut\UndoMobileSetAsPickedDeliveryNote;
use App\Actions\Api\Group\GoodsOut\UndoMobileWaitingDeliveryNote;
use App\Actions\Api\Group\GoodsOut\UndoMobileWaitingPickingSession;
use App\Actions\Api\Group\GoodsOut\UnpackMobileDeliveryNote;
use App\Actions\Api\Group\GoodsOut\UnpackMobileDeliveryNoteItem;
use App\Actions\Api\Group\GoodsOut\UpdateMobileDeliveryNoteDeliveryAddress;
use App\Actions\Api\Group\GoodsOut\UpdateMobileDeliveryNoteItemBoxes;
use App\Actions\Api\Group\GoodsOut\UpdateMobileDeliveryNoteNotes;
use App\Actions\Api\Group\GoodsOut\UpdateMobileDeliveryNotePacker;
use App\Actions\Api\Group\GoodsOut\UpdateMobileDeliveryNoteParcels;
use App\Actions\Api\Group\GoodsOut\UpdateMobileDeliveryNotePicker;
use App\Actions\HumanResources\ClockingMachine\ValidateClockingMachineQrCode;
use App\Actions\HumanResources\WorkSchedule\IndexShiftSchedules;
use App\Actions\Api\Group\Inventory\IndexMobileOrgStockFamilies;
use App\Actions\Api\Group\Inventory\IndexMobileOrgStockFamilySkos;
use App\Actions\Api\Group\Locations\AuditMobileLocation;
use App\Actions\Api\Group\Locations\GetMobileLocationsOverview;
use App\Actions\Api\Group\Locations\GetMobileStockMovementReasons;
use App\Actions\Api\Group\Locations\MoveAllMobileLocationOrgStocks;
use App\Actions\Api\Group\Locations\MoveSomeMobileLocationOrgStocks;
use App\Actions\Api\Group\Locations\RemoveMobileLocationOrgStocks;
use App\Actions\Api\Group\Locations\UpdateMobileLocationOrgStock;
use App\Actions\Api\Group\Locations\IndexMobileEmptyLocationOrgStocks;
use App\Actions\Api\Group\Locations\IndexMobileLocations;
use App\Actions\Api\Group\Locations\IndexMobileLocationStockMovements;
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
        Route::get('/warehouses/{warehouse:id}/org-stock-families', IndexMobileOrgStockFamilies::class)->name('warehouses.org_stock_families');
        Route::get('/warehouses/{warehouse:id}/org-stock-families/{orgStockFamily:id}/skos', IndexMobileOrgStockFamilySkos::class)->name('warehouses.org_stock_families.skos')->withoutScopedBindings();
        Route::get('/warehouses/{warehouse:id}/locations', IndexMobileLocations::class)->name('warehouses.locations');
        Route::get('/warehouses/{warehouse:id}/empty-location-org-stocks', IndexMobileEmptyLocationOrgStocks::class)->name('warehouses.empty_location_org_stocks');
        Route::get('/warehouses/{warehouse:id}/removed-empty-location-org-stocks', IndexMobileRemovedEmptyLocationOrgStocks::class)->name('warehouses.removed_empty_location_org_stocks');
        Route::patch('/warehouses/{warehouse:id}/empty-locations-removal', UpdateMobileEmptyLocationsRemoval::class)->name('warehouses.empty_locations_removal.update');
        Route::get('/locations/{location:id}', ShowMobileLocation::class)->name('locations.show');
        Route::get('/locations/{location:id}/stock-movements', IndexMobileLocationStockMovements::class)->name('locations.stock_movements');
        Route::post('/locations/{location:id}/audit', AuditMobileLocation::class)->name('locations.audit');
        Route::post('/locations/{location:id}/move-all', MoveAllMobileLocationOrgStocks::class)->name('locations.move_all');
        Route::post('/locations/{location:id}/move-some', MoveSomeMobileLocationOrgStocks::class)->name('locations.move_some');
        Route::post('/locations/{location:id}/remove-org-stocks', RemoveMobileLocationOrgStocks::class)->name('locations.remove_org_stocks');
        Route::patch('/location-org-stocks/{locationOrgStock:id}', UpdateMobileLocationOrgStock::class)->name('location_org_stocks.update');
        Route::get('/stock-movement-reasons', GetMobileStockMovementReasons::class)->name('stock_movement_reasons');

        Route::get('/warehouses/{warehouse:id}/delivery-notes', GetMobileDeliveryNotes::class)->name('warehouses.delivery_notes');
        Route::get('/warehouses/{warehouse:id}/delivery-notes/counts', GetMobileDeliveryNoteCounts::class)->name('warehouses.delivery_notes.counts');
        Route::get('/warehouses/{warehouse:id}/scan', GetMobileScan::class)->name('warehouses.scan');
        Route::get('/delivery-notes/{deliveryNote:id}', GetMobileDeliveryNote::class)->name('delivery_notes.show');
        Route::get('/warehouses/{warehouse:id}/trolleys', IndexMobileTrolleys::class)->name('warehouses.trolleys');
        Route::get('/warehouses/{warehouse:id}/picked-bays', IndexMobilePickedBays::class)->name('warehouses.picked_bays');
        Route::get('/warehouses/{warehouse:id}/pickers', IndexMobilePickers::class)->name('warehouses.pickers');
        Route::get('/warehouses/{warehouse:id}/dispatch-personnel', IndexMobileDispatchPersonnel::class)->name('warehouses.dispatch_personnel');
        Route::get('/warehouses/{warehouse:id}/picking-sessions', IndexMobilePickingSessions::class)->name('warehouses.picking_sessions');
        Route::post('/warehouses/{warehouse:id}/picking-sessions', StoreMobilePickingSession::class)->name('warehouses.picking_sessions.store');
        Route::get('/picking-sessions/{pickingSession:id}', GetMobilePickingSession::class)->name('picking_sessions.show');

        Route::prefix('picking-sessions/{pickingSession:id}')->name('picking_sessions.')->group(function () {
            Route::post('/start-picking', StartMobilePickingSession::class)->name('start_picking');
            Route::post('/back-to-picking', UndoMobileWaitingPickingSession::class)->name('back_to_picking');
            Route::post('/trolleys', AttachMobilePickingSessionTrolley::class)->name('trolleys.attach');
            Route::delete('/trolleys/{trolley:id}', DetachMobilePickingSessionTrolley::class)->name('trolleys.detach')->withoutScopedBindings();
            Route::post('/delivery-notes', AddMobilePickingSessionDeliveryNotes::class)->name('delivery_notes.add');
            Route::post('/delivery-notes/remove', RemoveMobilePickingSessionDeliveryNotes::class)->name('delivery_notes.remove');
            Route::post('/scan-pick', PickMobilePickingSessionItemByScan::class)->name('scan_pick');
            Route::post('/scan-pack', PackMobilePickingSessionItemByScan::class)->name('scan_pack');
            Route::post('/chat', OpenMobilePickingSessionChat::class)->name('chat');
        });

        Route::get('/chat/{staffConversation:ulid}/messages', IndexMobileStaffMessages::class)->name('chat.messages.index');
        Route::post('/chat/{staffConversation:ulid}/messages', SendMobileStaffMessage::class)->name('chat.messages.store');

        Route::prefix('delivery-notes/{deliveryNote:id}')->name('delivery_notes.')->group(function () {
            Route::post('/start-picking', StartMobilePicking::class)->name('start_picking');
            Route::post('/take-over', TakeOverMobileDeliveryNote::class)->name('take_over');
            Route::post('/set-as-picked', SetMobileDeliveryNoteAsPicked::class)->name('set_as_picked');
            Route::post('/set-as-packed', SetMobileDeliveryNoteAsPacked::class)->name('set_as_packed');
            Route::post('/put-back-incomplete-sets', PutBackMobileIncompleteSetParts::class)->name('put_back_incomplete_sets');
            Route::post('/auto-finish-waiting', AutoFinishMobileWaitingDeliveryNote::class)->name('auto_finish_waiting');
            Route::post('/back-to-picking', UndoMobileWaitingDeliveryNote::class)->name('back_to_picking');
            Route::post('/undo-set-as-picked', UndoMobileSetAsPickedDeliveryNote::class)->name('undo_set_as_picked');
            Route::post('/trolley', ChangeMobileDeliveryNoteTrolley::class)->name('trolley');
            Route::post('/picked-bay', ChangeMobileDeliveryNotePickedBay::class)->name('picked_bay');
            Route::post('/picker', UpdateMobileDeliveryNotePicker::class)->name('picker');
            Route::post('/scan-pick', PickMobileDeliveryNoteItemByScan::class)->name('scan_pick');
            Route::post('/start-packing', StartMobilePacking::class)->name('start_packing');
            Route::post('/undo-packing', UndoMobilePackingDeliveryNote::class)->name('undo_packing');
            Route::post('/unpack', UnpackMobileDeliveryNote::class)->name('unpack');
            Route::post('/parcels', UpdateMobileDeliveryNoteParcels::class)->name('parcels');
            Route::post('/scan-pack', PackMobileDeliveryNoteItemByScan::class)->name('scan_pack');
            Route::post('/finalise-and-dispatch', FinaliseAndDispatchMobileDeliveryNote::class)->name('finalise_and_dispatch');
            Route::post('/finalise', FinaliseMobileDeliveryNote::class)->name('finalise');
            Route::post('/dispatch', DispatchMobileDeliveryNote::class)->name('dispatch');
            Route::get('/shippers', IndexMobileShippers::class)->name('shippers');
            Route::post('/shipments', StoreMobileShipment::class)->name('shipments.store');
            Route::post('/shipments/retry-with-address', RetryMobileShipmentWithAddress::class)->name('shipments.retry_with_address');
            Route::post('/external-shipment', StoreMobileExternalShipment::class)->name('external_shipment');
            Route::post('/inserts/print', PrintMobileDeliveryNoteInserts::class)->name('inserts.print');
            Route::post('/inserts/{deliveryNoteLeaflet:id}/pdf', DownloadMobileDeliveryNoteInsertPdf::class)->name('inserts.pdf')->withoutScopedBindings();
            Route::get('/pdf', DownloadMobileDeliveryNotePdf::class)->name('pdf');
            Route::get('/packing-list.pdf', DownloadMobilePackingListPdf::class)->name('packing_list_pdf');
            Route::get('/gift-message.pdf', DownloadMobileGiftMessagePdf::class)->name('gift_message_pdf');
            Route::post('/cancel', CancelMobileDeliveryNote::class)->name('cancel');
            Route::post('/notes', UpdateMobileDeliveryNoteNotes::class)->name('notes');
            Route::post('/packer', UpdateMobileDeliveryNotePacker::class)->name('packer');
            Route::post('/delivery-address', UpdateMobileDeliveryNoteDeliveryAddress::class)->name('delivery_address');
            Route::post('/chat', OpenMobileDeliveryNoteChat::class)->name('chat');

            Route::prefix('shipments/{shipment:id}')->name('shipments.')->withoutScopedBindings()->group(function () {
                Route::delete('/', DeleteMobileShipment::class)->name('delete');
                Route::post('/print-label', PrintMobileShipmentLabel::class)->name('print_label');
                Route::get('/label', DownloadMobileShipmentLabel::class)->name('label');
            });

            Route::prefix('lines/{deliveryNoteItem:id}')->name('lines.')->withoutScopedBindings()->group(function () {
                Route::post('/pick', PickMobileDeliveryNoteItem::class)->name('pick');
                Route::post('/pick-all', PickAllMobileDeliveryNoteItem::class)->name('pick_all');
                Route::post('/not-picked', StoreMobileNotPickedPicking::class)->name('not_picked');
                Route::post('/waiting', SetMobileDeliveryNoteItemAsWaiting::class)->name('waiting');
                Route::delete('/pickings/{picking:id}', DeleteMobilePicking::class)->name('pickings.delete');
                Route::post('/pack', PackMobileDeliveryNoteItem::class)->name('pack');
                Route::post('/unpack', UnpackMobileDeliveryNoteItem::class)->name('unpack');
                Route::post('/boxes', UpdateMobileDeliveryNoteItemBoxes::class)->name('boxes');
            });
        });
    });
});
