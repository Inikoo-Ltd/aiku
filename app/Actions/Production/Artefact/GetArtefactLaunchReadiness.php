<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What an experiment still needs before it can be launched. Launching means it can be sold: its stock has a
 * product on sale, online, to partners or to customers, and the floor has a recipe to make it from. A trial
 * run is shown but not required.
 */
class GetArtefactLaunchReadiness
{
    use AsObject;

    /**
     * @return array{ready: bool, checks: array<int, array{key: string, label: string, done: bool, required: bool}>, products: array<int, array{code: string, shop: string|null}>}
     */
    public function handle(Artefact $artefact): array
    {
        $productsOnSale = $artefact->org_stock_id
            ? Product::whereHas('orgStocks', fn ($query) => $query->where('org_stocks.id', $artefact->org_stock_id))
                ->where('state', ProductStateEnum::ACTIVE)
                ->where('is_for_sale', true)
                ->with('shop:id,name')
                ->orderBy('code')
                ->get(['id', 'code', 'shop_id'])
            : collect();

        $checks = [
            [
                'key'      => 'recipe',
                'label'    => __('Recipe the floor makes it from'),
                'done'     => $artefact->manufactureTasks()->exists(),
                'required' => true,
            ],
            [
                'key'      => 'trial_run',
                'label'    => __('A trial run made and received'),
                'done'     => $artefact->jobOrderItems()->whereHas('jobOrder', fn ($query) => $query->where('state', JobOrderStateEnum::RECEIVED))->exists(),
                'required' => false,
            ],
            [
                'key'      => 'stock',
                'label'    => __('Linked to the SKO it is stocked as'),
                'done'     => $artefact->org_stock_id !== null,
                'required' => true,
            ],
            [
                'key'      => 'product',
                'label'    => __('A product on sale with that SKO'),
                'done'     => $productsOnSale->isNotEmpty(),
                'required' => true,
            ],
        ];

        return [
            'ready'    => collect($checks)->every(fn (array $check) => $check['done'] || !$check['required']),
            'checks'   => $checks,
            'products' => $productsOnSale->map(fn (Product $product) => [
                'code' => $product->code,
                'shop' => $product->shop?->name,
            ])->all(),
        ];
    }
}
