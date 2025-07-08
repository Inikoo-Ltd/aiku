<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:18:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateTradeUnitImages;
use App\Actions\Helpers\Media\AttachAttachmentToModel;
use App\Actions\Helpers\Media\DetachAttachmentFromModel;
use Illuminate\Support\Facades\Route;

Route::name('trade-unit.')->prefix('trade-unit/{tradeUnit:id}')->group(function () {
    Route::patch('update', UpdateTradeUnit::class)->name('update')->withoutScopedBindings();
    Route::patch('update-images', UpdateTradeUnitImages::class)->name('update_images')->withoutScopedBindings();

    Route::name('attachment.')->prefix('attachment')->group(function () {
        Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inTradeUnit'])->name('attach');
        Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inTradeUnit'])->name('detach')->withoutScopedBindings();
    });
});
