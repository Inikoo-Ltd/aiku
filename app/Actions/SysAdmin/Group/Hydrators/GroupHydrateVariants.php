<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateVariants implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {

        $stats = [
            'number_current_product_variants' => DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->whereIn('product_variants.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
                ->count(),

            'number_products_with_variants' => DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->distinct('products.id')
                ->count(),

            'number_current_products_with_variants' => DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->whereIn('product_variants.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
                ->distinct('products.id')
                ->count(),
        ];

        foreach (ProductStateEnum::cases() as $case) {
            $stats['number_product_variants_state_'.$case->snake()] = DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->where('product_variants.state', $case->value)
                ->count();

            $stats['number_products_with_variants_state_'.$case->snake()] = DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->where('product_variants.state', $case->value)
                ->distinct('products.id')
                ->count();
        }

        foreach (ProductStatusEnum::cases() as $case) {
            $stats['number_product_variants_status_'.$case->snake()] = DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->where('product_variants.status', $case->value)
                ->count();

            $stats['number_products_with_variants_status_'.$case->snake()] = DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->where('product_variants.status', $case->value)
                ->distinct('products.id')
                ->count();
        }

        foreach (ProductTradeConfigEnum::cases() as $case) {
            $stats['number_product_variants_trade_config_'.$case->snake()] = DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->where('product_variants.trade_config', $case->value)
                ->count();

            $stats['number_products_with_variants_trade_config_'.$case->snake()] = DB::table('products')
                ->where('products.is_main', true)
                ->where('products.group_id', $group->id)
                ->whereNull('products.deleted_at')
                ->join('products as product_variants', 'products.id', '=', 'product_variants.main_product_id')
                ->where('product_variants.trade_config', $case->value)
                ->distinct('products.id')
                ->count();
        }

        $group->catalogueStats()->update($stats);

    }
}
