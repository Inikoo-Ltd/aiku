<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncronisePortfolioToEbay extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser, Portfolio $portfolio)
    {
        RequestApiUploadProductEbay::run($ebayUser, $portfolio);
    }

    public function asController(EbayUser $ebayUser, Portfolio $portfolio, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($ebayUser, $portfolio);
    }
}
