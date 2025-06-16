<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Amazon\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncronisePortfolioToAmazon extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(AmazonUser $amazonUser, Portfolio $portfolio)
    {
        RequestApiUploadProductAmazon::dispatch($amazonUser, $portfolio);
    }

    public function asController(AmazonUser $amazonUser, Portfolio $portfolio, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($amazonUser, $portfolio);
    }
}
