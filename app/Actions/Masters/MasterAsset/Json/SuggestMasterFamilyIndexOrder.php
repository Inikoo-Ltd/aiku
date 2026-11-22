<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 18:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\Helpers\AI\AskJev;
use App\Actions\Masters\MasterProductCategory\UI\GetMasterFamilyIndexOrdering;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;

/**
 * Asks Jev, in one call, how high each product of the family should go on the family page of
 * every shop, then sorts by its score, ties broken by group sales.
 */
class SuggestMasterFamilyIndexOrder extends OrgAction
{
    use WithMastersEditAuthorisation;

    /** Jev answers one question per product within its 20 s timeout; bigger families sort by sales instead. */
    public const MAX_PRODUCTS = 100;

    private const RUBRIC = [
        'bottom of the page',
        'lower half',
        'middle',
        'upper half',
        'top of the page',
    ];

    /**
     * @return array{codes: array<int, string>, scores: array<string, float>}
     */
    public function handle(MasterProductCategory $masterFamily): array
    {
        if ($masterFamily->type !== MasterProductCategoryTypeEnum::FAMILY) {
            abort(403, 'Only families keep a product index');
        }

        $ordering = GetMasterFamilyIndexOrdering::run($masterFamily);
        $products = collect($ordering['products']);

        if ($products->count() > self::MAX_PRODUCTS) {
            abort(422, __('AI order works for families of up to :max products, sort this one by sales instead', ['max' => self::MAX_PRODUCTS]));
        }

        $state = [
            'family'           => $masterFamily->name,
            'open_shops_count' => $ordering['open_shops_count'],
            'currency'         => $ordering['currency_code'],
            'products'         => $products->map(fn (array $product) => [
                'code'                   => $product['code'],
                'name'                   => $product['name'],
                'group_sales_12_months'  => $product['sales_12m'],
                'units_sold_12_months'   => $product['sold_12m'],
                'shops_selling_it'       => $product['shops_selling'],
                'shops_with_stock'       => $product['shops_in_stock'],
            ])->values()->all(),
        ];

        $questions = $products->mapWithKeys(fn (array $product) => [
            $product['code'] => [
                'type'         => 'score',
                'instructions' => "These are the products of one family of a wholesale gift supplier, shown in the same order on the family page of {$ordering['open_shops_count']} shops in different countries. How high on the page should product {$product['code']} go so the family sells the most across all shops? Proven best sellers and products sold and in stock in most shops go higher; products few shops sell or have in stock go lower.",
                'criteria'     => self::RUBRIC,
            ],
        ])->all();

        $answers = AskJev::make()->handle($state, $questions);
        if ($answers === null) {
            abort(503, __('The AI suggestion is not available right now, try again later'));
        }

        $scores = $products->mapWithKeys(fn (array $product) => [$product['code'] => round((float) ($answers[$product['code']]['score'] ?? 0), 3)]);

        $codes = $products
            ->sort(fn (array $a, array $b) => [$scores[$b['code']], $b['sales_12m']] <=> [$scores[$a['code']], $a['sales_12m']])
            ->pluck('code')
            ->values()
            ->all();

        return [
            'codes'  => $codes,
            'scores' => $scores->all(),
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): array
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory);
    }
}
