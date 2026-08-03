<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateWebsiteSearchBoosts extends OrgAction
{
    use WithWebAuthorisation;

    public const array BOOSTABLE_TYPES = [
        'product'          => Product::class,
        'product_category' => ProductCategory::class,
        'collection'       => Collection::class,
    ];

    /**
     * Boost ids grouped by type for use at search time. Out-of-stock products are
     * excluded so sold-out items stop being promoted without staff intervention.
     *
     * @return array<string, array<int, int>>
     */
    public static function activeBoostIds(Website $website): array
    {
        $boosts = collect(Arr::get($website->settings, 'search_boosts', []))
            ->groupBy('type')
            ->map(fn ($items) => $items->pluck('id')->all())
            ->all();

        if ($productIds = Arr::get($boosts, 'product')) {
            $inStockIds = Product::whereIn('id', $productIds)
                ->where('available_quantity', '>', 0)
                ->pluck('id')
                ->all();

            if ($inStockIds) {
                $boosts['product'] = $inStockIds;
            } else {
                unset($boosts['product']);
            }
        }

        return $boosts;
    }

    /**
     * Current boosts enriched for display.
     *
     * @return array<int, array{type: string, id: int, code: string, name: string|null}>
     */
    public static function currentBoosts(Website $website): array
    {
        $boosts = Arr::get($website->settings, 'search_boosts', []);

        return array_values(array_filter(array_map(function (array $boost) {
            $modelClass = Arr::get(self::BOOSTABLE_TYPES, $boost['type']);
            $model      = $modelClass ? $modelClass::find($boost['id']) : null;

            if (!$model) {
                return null;
            }

            return [
                'type' => $boost['type'],
                'id'   => $model->id,
                'code' => $model->code,
                'name' => $model->name,
            ];
        }, $boosts)));
    }

    public function handle(Website $website, array $boosts): Website
    {
        $validBoosts = [];
        foreach ($boosts as $boost) {
            $modelClass = self::BOOSTABLE_TYPES[$boost['type']];
            if ($modelClass::where('shop_id', $website->shop_id)->whereKey($boost['id'])->exists()) {
                $validBoosts[] = [
                    'type' => $boost['type'],
                    'id'   => (int)$boost['id'],
                ];
            }
        }

        $settings = $website->settings;
        data_set($settings, 'search_boosts', $validBoosts);
        $website->update(['settings' => $settings]);

        return $website;
    }

    public function rules(): array
    {
        return [
            'boosts'        => ['present', 'array', 'max:3'],
            'boosts.*.type' => ['required', Rule::in(array_keys(self::BOOSTABLE_TYPES))],
            'boosts.*.id'   => ['required', 'integer'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($website, Arr::get($this->validatedData, 'boosts', []));
    }
}
