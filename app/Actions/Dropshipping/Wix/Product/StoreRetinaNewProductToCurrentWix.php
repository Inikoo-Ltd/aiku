<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreRetinaNewProductToCurrentWix extends RetinaAction
{
    use AsAction;

    public function handle(Portfolio $portfolio): void
    {
        StoreProductToWix::run($portfolio);
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($portfolio);
    }
}
