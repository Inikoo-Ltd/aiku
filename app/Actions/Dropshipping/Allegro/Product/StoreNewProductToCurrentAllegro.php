<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreNewProductToCurrentAllegro extends OrgAction implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'allegro';

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
        return StoreProductToAllegro::run($portfolio);
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);
        $this->handle($portfolio);
    }
}
