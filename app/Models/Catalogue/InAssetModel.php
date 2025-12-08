<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Apr 2024 19:31:40 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Models\Helpers\Currency;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait InAssetModel
{
    use InShop;

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function historicAssets(): MorphMany
    {
        return $this->morphMany(HistoricAsset::class, 'model');
    }

    public function historicAsset(): BelongsTo
    {
        return $this->belongsTo(HistoricAsset::class, 'current_historic_asset_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
