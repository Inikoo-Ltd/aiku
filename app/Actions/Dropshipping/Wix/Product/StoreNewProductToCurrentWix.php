<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreNewProductToCurrentWix extends OrgAction implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'dropshipping-long';

    public function getJobUniqueId(Portfolio $portfolio): int
    {
        return $portfolio->id;
    }

    public function asJob(Portfolio $portfolio): void
    {
        $this->handle($portfolio);
    }

    public function handle(Portfolio $portfolio): Portfolio
    {
        return StoreProductToWix::run($portfolio);
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);

        $this->handle($portfolio);
    }
}
