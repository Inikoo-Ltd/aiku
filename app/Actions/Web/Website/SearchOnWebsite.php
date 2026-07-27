<?php

namespace App\Actions\Web\Website;

use App\Actions\IrisAction;
use App\Actions\Web\Website\Layouts\FetchUsedProductsWebBlock;
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
        $webBlockCode = null;

        if ($website) {
            $layout = $website->liveProductsSnapshot?->layout;

            $webBlockData = data_get($layout, 'data');
            $webBlockCode = FetchUsedProductsWebBlock::run($website);
        }
        return Inertia::render('Search', [
            'web_block_family'       => $webBlockData,
            'web_block_family_code'  => $webBlockCode,
            ...$dataList,
        ]);
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle();
    }
}
