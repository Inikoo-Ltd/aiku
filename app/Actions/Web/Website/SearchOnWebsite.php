<?php

namespace App\Actions\Web\Website;

use App\Actions\IrisAction;
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
            $layout = $website->liveProductsSnapshot->layout;

            $webBlockData = data_get($layout, 'data');
        };
        return Inertia::render('Search', [
            'web_block_family'  => $webBlockData,
            ...$dataList,
        ]);
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle();
    }
}
