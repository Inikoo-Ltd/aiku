<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformSalesIntervals;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    // Note: Experimental Data (Need to be checked)
    public function toArray($request): array
    {
        /** @var Shop|Platform $model */
        $model = $this->resource;

        $platformsIntervals = [];

        if ($model instanceof Shop) {
            $platformsIntervals = PlatformShopSalesIntervals::where('shop_id', $model->id)->get();
        }

        if ($model instanceof Platform) {
            $platformsIntervals = PlatformSalesIntervals::where('platform_id', $model->id)->get();
        }

        return [
            'slug'      => $model->slug,
            'columns'   => []
        ];
    }
}
