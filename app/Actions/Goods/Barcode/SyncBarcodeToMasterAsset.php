<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Barcode;

use App\Actions\Goods\Barcode\Hydrators\GroupHydrateBarcodes;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Models\Goods\ModelHasBarcode;
use App\Models\Helpers\Barcode;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A master built from several trade units is the only place a bundle GTIN can live, so the
 * pool records it against the master like it records a trade unit's. A barcode taken off a
 * master stays used: it may already be published, and a GTIN is never handed out twice.
 */
class SyncBarcodeToMasterAsset
{
    use AsAction;

    public function handle(MasterAsset $masterAsset): void
    {
        ModelHasBarcode::where('model_type', $masterAsset->getMorphClass())
            ->where('model_id', $masterAsset->id)
            ->where('status', true)
            ->update([
                'status'       => false,
                'withdrawn_at' => now(),
            ]);

        $barcode = blank($masterAsset->barcode) ? null : Barcode::where('group_id', $masterAsset->group_id)
            ->where('number', $masterAsset->barcode)
            ->first();

        if (!$barcode) {
            return;
        }

        ModelHasBarcode::create([
            'type'       => $barcode->type,
            'status'     => true,
            'barcode_id' => $barcode->id,
            'model_type' => $masterAsset->getMorphClass(),
            'model_id'   => $masterAsset->id,
        ]);

        $barcode->update([
            'status'      => BarcodeStatusEnum::USED,
            'assigned_at' => now(),
        ]);

        GroupHydrateBarcodes::dispatch($barcode->group);
    }
}
