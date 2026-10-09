<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Goods\Packaging\DeleteEprManualLine;
use App\Actions\Goods\Packaging\SetOrgStockShipmentPackaging;
use App\Actions\Goods\Packaging\StoreEprManualLine;
use App\Actions\Goods\Packaging\UnsetOrgStockShipmentPackaging;
use Illuminate\Support\Facades\Route;

Route::post('organisation/{organisation:id}/epr-manual-lines', StoreEprManualLine::class)->name('epr_manual_line.store');
Route::delete('organisation/{organisation:id}/epr-manual-lines/{eprManualLine:id}', DeleteEprManualLine::class)->name('epr_manual_line.delete')->withoutScopedBindings();
Route::post('organisation/{organisation:id}/shipment-packaging', SetOrgStockShipmentPackaging::class)->name('shipment_packaging.store');
Route::delete('organisation/{organisation:id}/shipment-packaging/{orgStock:id}', UnsetOrgStockShipmentPackaging::class)->name('shipment_packaging.delete')->withoutScopedBindings();
