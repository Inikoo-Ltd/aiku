<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 18:34:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Platform\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Platform;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PlatformHydratePortfolios implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Platform $platform): string
    {
        return $platform->id;
    }

    public function handle(Platform $platform): void
    {
        $query = DB::table('portfolios')
            ->where('item_type', class_basename(Product::class))
            ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
            ->where('platform_id', $platform->id)
            ->distinct('item_id');
        $stats = [
            'number_products' => $query->count('product_id')

        ];

        foreach (ProductStateEnum::cases() as $state) {
            $stats['number_products_state_' . $state->value] = $query->where('products.state', $state->value)->count('product_id');
        }


        $platform->stats()->update($stats);
    }
}
