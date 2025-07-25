<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Shopify;

use App\Actions\Dropshipping\Shopify\Product\MatchPortfolioToCurrentShopifyProduct;
use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Events\UploadProductToShopifyProgressEvent;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchRetinaPortfolioToCurrentShopifyProduct extends RetinaAction
{
    use AsAction;

    public function handle(Portfolio $portfolio, array $modelData)
    {
        MatchPortfolioToCurrentShopifyProduct::run($portfolio, $modelData);
    }


    public function rules(): array
    {
        return [
            'shopify_product_id' => ['required', 'string'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($request);
        $this->handle($portfolio, $this->validatedData);
    }

}
