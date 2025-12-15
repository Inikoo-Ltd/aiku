<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateOrdersStats implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int|null $assetID): string
    {
        return $assetID ?? 'empty';
    }

    public function handle(int|null $assetID): void
    {
        if (!$assetID) {
            return;
        }

        $asset = Asset::find($assetID);
        if (!$asset) {
            return;
        }


        $stats = [
            'number_orders' => DB::table('transactions')->where('asset_id', $asset->id)->distinct()->count('order_id'),
            'last_order_created_at'    => DB::table('transactions')->where('asset_id', $asset->id)->max('created_at'),
        ];

        $asset->orderingStats()->update($stats);
    }
}
