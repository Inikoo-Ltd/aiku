<?php

namespace App\Actions\Web\Website;

use App\Actions\IrisAction;
use App\Models\Web\Website;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class SearchOnWebsite extends IrisAction
{
    public function handle()
    {   
        $webBlockData = [];
        if ($this->website) {
            $layout = $this->website->liveProductsSnapshot->layout;

            $webBlockData = data_get($layout, 'data.fieldValue');
        };

        return [
            'data'  => $webBlockData,
        ];
    }

    public function htmlResponse(array $dataList)
    {
        return Inertia::render('Search', $dataList);
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle();
    }
}
