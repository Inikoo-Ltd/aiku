<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\IrisAction;
use App\Actions\Web\Website\UpdateWebsiteSearchBoosts;
use App\Actions\Web\Website\UpdateWebsiteSearchFeaturedItems;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * The featured items of the website in the same shape as a catalogue search response, so the
 * storefront can show them while the search field is still empty.
 */
class GetIrisSearchFeaturedItems extends IrisAction
{
    use WithIrisSearchEnrichedItems;

    private const array RESULT_KEYS = [
        'product'          => 'products',
        'product_category' => 'product_categories',
        'collection'       => 'collections',
    ];

    public function handle(): array
    {
        $featuredItemIds = UpdateWebsiteSearchFeaturedItems::activeFeaturedItemIds($this->website);

        $results = [];
        foreach (self::RESULT_KEYS as $type => $resultKey) {
            $modelClass = UpdateWebsiteSearchBoosts::BOOSTABLE_TYPES[$type];

            $results[$resultKey] = $this->enrichItems(
                $this->getFeaturedItems($modelClass, Arr::get($featuredItemIds, $type, [])),
                $modelClass,
                largeImage: $type === 'product',
                withOffers: $type === 'product'
            );
        }

        return ['results' => $results];
    }

    /**
     * @param class-string $modelClass
     * @param array<int, int> $ids
     *
     * @return array<int, array{id: int, code: string, name: string|null}>
     */
    private function getFeaturedItems(string $modelClass, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $models = $modelClass::query()
            ->where('shop_id', $this->shop->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $models->get($id))
            ->filter()
            ->map(fn ($model) => [
                'id'   => $model->id,
                'code' => $model->code,
                'name' => $model->name,
            ])
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle();
    }
}
