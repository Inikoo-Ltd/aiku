<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Featured items are what the storefront search shows while the field is still empty, unlike
 * boosts, which only promote an item once the query already matches it.
 */
class UpdateWebsiteSearchFeaturedItems extends OrgAction
{
    use WithWebAuthorisation;

    public const array MAX_FEATURED_ITEMS_PER_TYPE = [
        'product'          => 7,
        'product_category' => 4,
        'collection'       => 4,
    ];

    /**
     * Featured item ids grouped by type for use at search time. Out-of-stock products are
     * excluded so sold-out items stop being featured without staff intervention.
     *
     * @return array<string, array<int, int>>
     */
    public static function activeFeaturedItemIds(Website $website): array
    {
        $featuredItems = collect(Arr::get($website->settings, 'search_featured_items', []))
            ->groupBy('type')
            ->map(fn ($items) => $items->pluck('id')->all())
            ->all();

        if ($productIds = Arr::get($featuredItems, 'product')) {
            $inStockIds = Product::whereIn('id', $productIds)
                ->where('available_quantity', '>', 0)
                ->pluck('id')
                ->all();

            if ($inStockIds) {
                $featuredItems['product'] = $inStockIds;
            } else {
                unset($featuredItems['product']);
            }
        }

        return $featuredItems;
    }

    /**
     * Current featured items enriched for display.
     *
     * @return array<int, array{type: string, id: int, code: string, name: string|null}>
     */
    public static function currentFeaturedItems(Website $website): array
    {
        $featuredItems = Arr::get($website->settings, 'search_featured_items', []);

        return array_values(array_filter(array_map(function (array $featuredItem) {
            $modelClass = Arr::get(UpdateWebsiteSearchBoosts::BOOSTABLE_TYPES, $featuredItem['type']);
            $model      = $modelClass ? $modelClass::find($featuredItem['id']) : null;

            if (!$model) {
                return null;
            }

            return [
                'type' => $featuredItem['type'],
                'id'   => $model->id,
                'code' => $model->code,
                'name' => $model->name,
            ];
        }, $featuredItems)));
    }

    public function handle(Website $website, array $featuredItems): Website
    {
        $countPerType       = [];
        $validFeaturedItems = [];
        foreach ($featuredItems as $featuredItem) {
            $type = $featuredItem['type'];
            if (Arr::get($countPerType, $type, 0) >= Arr::get(self::MAX_FEATURED_ITEMS_PER_TYPE, $type, 0)) {
                continue;
            }

            $modelClass = UpdateWebsiteSearchBoosts::BOOSTABLE_TYPES[$type];
            if ($modelClass::where('shop_id', $website->shop_id)->whereKey($featuredItem['id'])->exists()) {
                $countPerType[$type]  = Arr::get($countPerType, $type, 0) + 1;
                $validFeaturedItems[] = [
                    'type' => $type,
                    'id'   => (int)$featuredItem['id'],
                ];
            }
        }

        $settings = $website->settings;
        data_set($settings, 'search_featured_items', $validFeaturedItems);
        $website->update(['settings' => $settings]);

        BreakWebsiteIrisCache::run($website);

        return $website;
    }

    public function rules(): array
    {
        return [
            'featured_items'        => ['present', 'array', 'max:'.array_sum(self::MAX_FEATURED_ITEMS_PER_TYPE)],
            'featured_items.*.type' => ['required', Rule::in(array_keys(UpdateWebsiteSearchBoosts::BOOSTABLE_TYPES))],
            'featured_items.*.id'   => ['required', 'integer'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($website, Arr::get($this->validatedData, 'featured_items', []));
    }
}
