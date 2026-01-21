<?php

namespace App\Actions\Catalogue\Shop\UI;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopTopPerformanceStats
{
    use AsObject;

    public function handle(Shop $shop, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($fromDate && $toDate) {
            $startDate = Carbon::createFromFormat('Ymd', $fromDate)->startOfDay();
            $endDate = Carbon::createFromFormat('Ymd', $toDate)->endOfDay();
            
            return [
                'top_packers' => [
                    'ctm' => $this->getTopPackers($shop, [$startDate, $endDate])
                ],
                'top_pickers' => [
                    'ctm' => $this->getTopPickers($shop, [$startDate, $endDate])
                ]
            ];
        }

        $packers = [];
        $pickers = [];

        foreach (DateIntervalEnum::casesWithoutCustom() as $interval) {
            $packers[$interval->value] = $this->getTopPackers($shop, $interval);
            $pickers[$interval->value] = $this->getTopPickers($shop, $interval);
        }
        
        return [
            'top_packers' => $packers,
            'top_pickers' => $pickers,
        ];
    }

    protected function getTopPackers(Shop $shop, DateIntervalEnum|array $period): array
    {
        $query = Packing::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('packer_user_id')
            ->select('packer_user_id', DB::raw('count(*) as total_packed'))
            ->groupBy('packer_user_id')
            ->orderByDesc('total_packed')
            ->with('packer:id,contact_name,email')
            ->limit(5);

        if ($period instanceof DateIntervalEnum) {
            $period->wherePeriod($query, 'updated_at');
        } elseif (is_array($period) && count($period) === 2) {
            $query->whereBetween('updated_at', $period);
        }

        return $query->get()->map(function ($item) {
            return [
                'user'  => $item->packer,
                'count' => $item->total_packed,
            ];
        })->toArray();
    }

    protected function getTopPickers(Shop $shop, DateIntervalEnum|array $period): array
    {
        $query = Picking::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('picker_user_id')
            ->select('picker_user_id', DB::raw('count(*) as total_picked'))
            ->groupBy('picker_user_id')
            ->orderByDesc('total_picked')
            ->with('picker:id,contact_name,email')
            ->limit(5);

        if ($period instanceof DateIntervalEnum) {
            $period->wherePeriod($query, 'updated_at');
        } elseif (is_array($period) && count($period) === 2) {
            $query->whereBetween('updated_at', $period);
        }

        return $query->get()->map(function ($item) {
            return [
                'user'  => $item->picker,
                'count' => $item->total_picked,
            ];
        })->toArray();
    }
}
