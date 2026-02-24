<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateTopProductCategory implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'top_1d_department_id'  => null,
            'top_1d_family_id'      => null,
            'top_1d_product_id'     => null,
            'top_1w_department_id'  => null,
            'top_1w_family_id'      => null,
            'top_1w_product_id'     => null,
            'top_1m_department_id'  => null,
            'top_1m_family_id'      => null,
            'top_1m_product_id'     => null,
            'top_1y_department_id'  => null,
            'top_1y_family_id'      => null,
            'top_1y_product_id'     => null,
            'top_all_department_id' => null,
            'top_all_family_id'     => null,
            'top_all_product_id'    => null,
        ];

        $periods = [
            '1d'  => now()->subDay(),
            '1w'  => now()->startOfWeek(),
            '1m'  => now()->startOfMonth(),
            '1y'  => now()->startOfYear(),
            'all' => null,
        ];

        foreach ($periods as $periodKey => $periodDate) {
            $query = DB::table('invoice_transactions')
                ->where('shop_id', $shop->id)
                ->where('model_type', 'Product')
                ->whereNull('deleted_at');

            if ($periodDate) {
                $query->where('date', '>=', $periodDate);
            }

            $topProduct = (clone $query)
                ->whereNotNull('model_id')
                ->select('model_id')
                ->selectRaw('SUM(net_amount) as total_amount')
                ->groupBy('model_id')
                ->orderByDesc('total_amount')
                ->first();

            if ($topProduct) {
                $stats["top_{$periodKey}_product_id"] = $topProduct->model_id;
            }

            $topFamily = (clone $query)
                ->whereNotNull('family_id')
                ->select('family_id')
                ->selectRaw('SUM(net_amount) as total_amount')
                ->groupBy('family_id')
                ->orderByDesc('total_amount')
                ->first();

            if ($topFamily) {
                $stats["top_{$periodKey}_family_id"] = $topFamily->family_id;
            }

            $topDepartment = (clone $query)
                ->whereNotNull('department_id')
                ->select('department_id')
                ->selectRaw('SUM(net_amount) as total_amount')
                ->groupBy('department_id')
                ->orderByDesc('total_amount')
                ->first();

            if ($topDepartment) {
                $stats["top_{$periodKey}_department_id"] = $topDepartment->department_id;
            }
        }

        $shop->stats()->update($stats);
    }
}
