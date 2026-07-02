<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchPortfolioToCurrentAllegroProduct extends OrgAction
{
    use AsAction;

    public function handle(Portfolio $portfolio, array $modelData): void
    {
        $allegroProductId = Arr::get($modelData, 'platform_product_id');

        $portfolio->update([
            'platform_product_id'         => $allegroProductId,
            'platform_product_variant_id' => $allegroProductId
        ]);

        $portfolio->refresh();

        CheckAllegroPortfolio::run($portfolio);
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
