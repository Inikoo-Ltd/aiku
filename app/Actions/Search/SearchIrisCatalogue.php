<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\IrisAction;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class SearchIrisCatalogue extends IrisAction
{
    public function handle(string $query): array
    {
        $results = Search::run('catalogue', $query, ['shop_id' => $this->shop->id]);

        data_set($results, 'results.products', $this->enrichItems(Arr::get($results, 'results.products', []), Product::class, largeImage: true));
        data_set($results, 'results.product_categories', $this->enrichItems(Arr::get($results, 'results.product_categories', []), ProductCategory::class));
        data_set($results, 'results.collections', $this->enrichItems(Arr::get($results, 'results.collections', []), Collection::class));

        return $results;
    }

    /**
     * Attach the storefront canonical url and an image to each search hit.
     * Products use a larger 150x150 image; categories and collections keep the small thumbnail.
     *
     * @param array<int, array<string, mixed>> $items
     * @param class-string $modelClass
     *
     * @return array<int, array<string, mixed>>
     */
    private function enrichItems(array $items, string $modelClass, bool $largeImage = false): array
    {
        $ids = array_filter(array_column($items, 'id'));
        if (empty($ids)) {
            return $items;
        }

        $models = $modelClass::query()
            ->whereIn('id', $ids)
            ->with(['webpage' => fn ($query) => $query->where('website_id', $this->website->id)->with('shop')])
            ->get()
            ->keyBy('id');

        return array_map(static function (array $item) use ($models, $largeImage) {
            $model = $models->get($item['id']);

            $image = $largeImage
                ? $model?->imageSources(150, 150)
                : Arr::get($model?->web_images ?? [], 'main.thumbnail');

            $item['url']   = $model?->webpage?->getCanonicalUrl() ?: null;
            $item['image'] = $image ?: $item['image'] ?? null;

            return $item;
        }, $items);
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->validatedData['q']);
    }
}
