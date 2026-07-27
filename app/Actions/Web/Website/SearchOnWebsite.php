<?php

namespace App\Actions\Web\Website;

use App\Actions\IrisAction;
use App\Actions\Web\Website\Layouts\FetchUsedProductsWebBlock;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class SearchOnWebsite extends IrisAction
{
    public function handle(): array
    {
        $layout = $this->website->liveProductsSnapshot?->layout;
        $webBlockData = data_get($layout, 'data.fieldValue', []);

        return [
            'data' => $webBlockData,
        ];
    }

    public function htmlResponse(array $dataList)
    {
        $website = request()->website;
        $webBlockData = [];

        if ($website) {
            $webBlockData = data_get($website->liveProductsSnapshot?->layout, 'data');
        }

        // If search model is 'internal'
        if ($website && Arr::get($website->settings, 'iris_search_model', 'luigi') === 'internal') {
            return Inertia::render('SearchInternal', [
                'web_block_family'      => $webBlockData,
                'web_block_family_code' => FetchUsedProductsWebBlock::run($website),
                ...$dataList,
            ]);
        }

        return Inertia::render('Search', [
            'web_block_family' => $webBlockData,
            ...$dataList,
        ]);
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle();
    }
}
