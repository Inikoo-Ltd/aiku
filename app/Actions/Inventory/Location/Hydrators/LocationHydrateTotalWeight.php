<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-13h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Inventory\Location\Hydrators;

use App\Models\Inventory\Location;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class LocationHydrateTotalWeight implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Location $location): string
    {
        return $location->id;
    }

    public function handle(Location $location): void
    {
        $stats = [
            'total_weight' => DB::table('location_org_stocks')
                ->join('org_stocks', 'location_org_stocks.org_stock_id', '=', 'org_stocks.id')
                ->join('stocks', 'org_stocks.stock_id', '=', 'stocks.id')
                ->where('location_org_stocks.location_id', $location->id)
                ->sum('stocks.gross_weight')
        ];

        $location->stats()->update($stats);
    }

}
