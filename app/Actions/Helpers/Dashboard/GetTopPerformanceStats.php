<?php

namespace App\Actions\Helpers\Dashboard;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTopPerformanceStats
{
    use AsObject;

    public function handle(Shop|Organisation|Group $model, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($fromDate && $toDate) {
            $startDate = Carbon::createFromFormat('Ymd', $fromDate)->startOfDay();
            $endDate = Carbon::createFromFormat('Ymd', $toDate)->endOfDay();

            return [
                'top_packers' => [
                    'ctm' => $this->getTopPackers($model, [$startDate, $endDate])
                ],
                'top_pickers' => [
                    'ctm' => $this->getTopPickers($model, [$startDate, $endDate])
                ]
            ];
        }

        $packers = [];
        $pickers = [];

        foreach (DateIntervalEnum::casesWithoutCustom() as $interval) {
            $packers[$interval->value] = $this->getTopPackers($model, $interval);
            $pickers[$interval->value] = $this->getTopPickers($model, $interval);
        }

        return [
            'top_packers' => $packers,
            'top_pickers' => $pickers,
        ];
    }

    protected function getTopPackers(Shop|Organisation|Group $model, DateIntervalEnum|array $period): array
    {
        $foreignKey = $this->getForeignKey($model);

        $query = Packing::query()
            ->where($foreignKey, $model->id)
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

    protected function getTopPickers(Shop|Organisation|Group $model, DateIntervalEnum|array $period): array
    {
        $foreignKey = $this->getForeignKey($model);

        $query = Picking::query()
            ->where($foreignKey, $model->id)
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

    protected function getForeignKey(Shop|Organisation|Group $model): string
    {
        return match (get_class($model)) {
            Shop::class => 'shop_id',
            Organisation::class => 'organisation_id',
            Group::class => 'group_id',
            default => 'shop_id',
        };
    }
}
