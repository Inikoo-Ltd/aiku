<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 02:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterFamilyHydrateProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $masterFamilyId): string
    {
        return $masterFamilyId ?? 'empty';
    }

    public function handle(?int $masterFamilyId): void
    {
        $masterFamily = $masterFamilyId ? MasterProductCategory::find($masterFamilyId) : null;
        if (!$masterFamily) {
            return;
        }

        $where = fn ($query) => $query->where('is_main', true)
            ->whereNull('exclusive_for_customer_id')
            ->whereIn('master_product_id', DB::table('master_assets')->where('master_family_id', $masterFamily->id)->select('id'));

        $stats = [
            ...$this->getEnumStats(model: 'products', field: 'state', enum: ProductStateEnum::class, models: Product::class, where: $where),
            ...$this->getEnumStats(model: 'products', field: 'status', enum: ProductStatusEnum::class, models: Product::class, where: $where),
        ];

        $stats['number_products']         = array_sum(Arr::only($stats, array_map(fn (ProductStateEnum $state) => 'number_products_state_'.$state->snake(), ProductStateEnum::cases())));
        $stats['number_current_products'] = $stats['number_products_state_active'] + $stats['number_products_state_discontinuing'];

        $masterFamily->stats()->update($stats);
    }
}
