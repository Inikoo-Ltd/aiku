<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ebay;

use App\Actions\Dropshipping\Ebay\Product\StoreNewProductToCurrentEbay;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAndUploadRetinaPortfolioToCurrentEbay extends RetinaAction
{
    use AsAction;

    public function handle(Portfolio $portfolio, array $modelData, $isDraft = false): void
    {
        $portfolio = UpdatePortfolio::run($portfolio, $modelData);

        if (! $isDraft) {
            StoreNewProductToCurrentEbay::run($portfolio->customerSalesChannel->user, $portfolio);
        }
    }

    public function rules(): array
    {
        return [
            'customer_product_name' => ['sometimes', 'string'],
            'customer_price' => ['sometimes', 'string'],
            'customer_description' => ['sometimes', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('customer_product_name', $request->input('title'));
        $this->set('customer_price', $request->input('price'));
        $this->set('customer_description', $request->input('description'));
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($request);
        $this->handle($portfolio, $this->validatedData);
    }

    public function asDraft(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($portfolio, $this->validatedData, true);
    }

}
