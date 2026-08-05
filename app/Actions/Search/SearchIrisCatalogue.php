<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\IrisAction;
use App\Actions\Web\Website\UpdateWebsiteSearchBoosts;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class SearchIrisCatalogue extends IrisAction
{
    use WithIrisSearchEnrichedItems;

    public function handle(string $query): array
    {
        $boosts = UpdateWebsiteSearchBoosts::activeBoostIds($this->website);

        $results = Search::run('catalogue', $query, [
            'shop_id'       => $this->shop->id,
            'is_in_website' => true,
            'boosts'        => $boosts,
            'language'      => $this->shop->language->code,
        ]);

        data_set($results, 'results.products', $this->enrichItems(Arr::get($results, 'results.products', []), Product::class, largeImage: true));
        data_set($results, 'results.product_categories', $this->enrichItems(Arr::get($results, 'results.product_categories', []), ProductCategory::class));
        data_set($results, 'results.collections', $this->enrichItems(Arr::get($results, 'results.collections', []), Collection::class));

        return $results;
    }

    public function rules(): array
    {
        return [
            'q'      => ['required', 'string', 'max:100'],
            'source' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        $results = $this->handle($this->validatedData['q']);

        $resultsCount = collect(Arr::get($results, 'results', []))->sum(fn ($items) => is_array($items) ? count($items) : 0);

        $armCounts = Arr::pull($results, 'arm_counts');

        $results['search_log_ulid'] = $this->recordWebsiteSearchLog(
            $request,
            'catalogue',
            $this->validatedData['q'],
            $resultsCount,
            $armCounts
        );

        return $results;
    }
}
