<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchPortfolioToCurrentWixProduct extends OrgAction
{
    use AsAction;

    public function handle(Portfolio $portfolio, array $modelData): void
    {
        $wixProductId = Arr::get($modelData, 'platform_product_id');

        $portfolio->update([
            'platform_product_id'         => $wixProductId,
            'platform_product_variant_id' => $wixProductId
        ]);

        $portfolio->refresh();

        CheckWixPortfolio::run($portfolio);
    }

    public function rules(): array
    {
        return [
            'platform_product_id' => ['required', 'string'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);

        $this->handle($portfolio, $this->validatedData);
    }
}
