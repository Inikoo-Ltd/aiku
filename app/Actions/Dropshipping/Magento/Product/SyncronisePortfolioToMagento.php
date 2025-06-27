<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Magento\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncronisePortfolioToMagento extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(MagentoUser $magentoUser, Portfolio $portfolio)
    {
        RequestApiUploadProductMagento::dispatch($magentoUser, $portfolio);
    }

    public function asController(MagentoUser $magentoUser, Portfolio $portfolio, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($magentoUser, $portfolio);
    }
}
