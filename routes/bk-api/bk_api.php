<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 15:46:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Transfers\Aurora\Api\ProcessAuroraAgent;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraBarcode;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCharge;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCredit;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCustomer;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCustomerNote;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDeleteDeliveryNote;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDeleteFavourites;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDeleteInvoice;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDeliveryNote;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDepartment;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDispatchedEmail;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraEmailTrackingEvent;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraEmployee;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraFamily;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraFavourites;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraFeedback;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraInvoice;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraLocation;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraMailshot;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOffer;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOfferCampaign;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOfferComponent;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOrder;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOrgStockMovement;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraPayment;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraProduct;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraProspect;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraPurchaseOrder;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraPurge;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraShop;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraStock;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraStockDelivery;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraStockFamily;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraSupplier;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraSupplierProduct;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraTimesheet;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraWarehouse;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraWarehouseArea;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraWebUser;
use Illuminate\Support\Facades\Route;

Route::name('bk_api.')->group(function () {
    Route::middleware(['auth:sanctum', 'ability:aurora'])->group(function () {
        Route::name('fetch.')->prefix('{organisation}')->group(function () {
            Route::post('agent', ProcessAuroraAgent::class)->name('agent');
            Route::post('barcode', ProcessAuroraBarcode::class)->name('barcode');
            Route::post('charge', ProcessAuroraCharge::class)->name('charge');
            Route::post('credit', ProcessAuroraCredit::class)->name('credit');
            Route::post('customer-notes', ProcessAuroraCustomerNote::class)->name('customer_note');
            Route::post('customer', ProcessAuroraCustomer::class)->name('customer');
            Route::post('delivery-note', ProcessAuroraDeliveryNote::class)->name('delivery_note');
            Route::post('delete-delivery-note', ProcessAuroraDeleteDeliveryNote::class)->name('delivery_note.delete');
            Route::post('department', ProcessAuroraDepartment::class)->name('department');
            Route::post('dispatched-email', ProcessAuroraDispatchedEmail::class)->name('dispatched_email');
            Route::post('email-tracking-event', ProcessAuroraEmailTrackingEvent::class)->name('email_tracking_event');
            Route::post('employee', ProcessAuroraEmployee::class)->name('employee');
            Route::post('family', ProcessAuroraFamily::class)->name('family');
            Route::post('feedback', ProcessAuroraFeedback::class)->name('feedback');
            Route::post('invoice', ProcessAuroraInvoice::class)->name('invoice');
            Route::post('delete-invoice', ProcessAuroraDeleteInvoice::class)->name('invoice.delete');
            Route::post('location', ProcessAuroraLocation::class)->name('location');
            Route::post('mailshot', ProcessAuroraMailshot::class)->name('mailshot');
            Route::post('offer-campaign', ProcessAuroraOfferCampaign::class)->name('offer_campaign');
            Route::post('offer-component', ProcessAuroraOfferComponent::class)->name('offer_component');
            Route::post('offer', ProcessAuroraOffer::class)->name('offer');
            Route::post('order', ProcessAuroraOrder::class)->name('order');
            Route::post('org-stock-movement', ProcessAuroraOrgStockMovement::class)->name('org_stock_movement');
            Route::post('payment', ProcessAuroraPayment::class)->name('payment');
            Route::post('product', ProcessAuroraProduct::class)->name('product');
            Route::post('prospect', ProcessAuroraProspect::class)->name('prospect');
            Route::post('purchase-order', ProcessAuroraPurchaseOrder::class)->name('purchase_order');
            Route::post('purge', ProcessAuroraPurge::class)->name('purge');
            Route::post('shop', ProcessAuroraShop::class)->name('shop');
            Route::post('stock-delivery', ProcessAuroraStockDelivery::class)->name('stock_delivery');
            Route::post('stock-family', ProcessAuroraStockFamily::class)->name('stock_family');
            Route::post('stock', ProcessAuroraStock::class)->name('stock');
            Route::post('supplier-product', ProcessAuroraSupplierProduct::class)->name('supplier_product');
            Route::post('supplier', ProcessAuroraSupplier::class)->name('supplier');
            Route::post('timesheet', ProcessAuroraTimesheet::class)->name('timesheet');
            Route::post('warehouse-area', ProcessAuroraWarehouseArea::class)->name('warehouse_area');
            Route::post('warehouse', ProcessAuroraWarehouse::class)->name('warehouse');
            Route::post('favourite', ProcessAuroraFavourites::class)->name('favourites');
            Route::post('delete-favourite', ProcessAuroraDeleteFavourites::class)->name('favourites.delete');
            Route::post('web-user', ProcessAuroraWebUser::class)->name('web_user');

        });
    });
});
