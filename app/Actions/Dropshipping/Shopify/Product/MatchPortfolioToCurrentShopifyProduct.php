<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Portfolio\UnlinkRetinaPortfolio;
use App\Events\UploadProductToShopifyProgressEvent;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchPortfolioToCurrentShopifyProduct extends OrgAction
{
    use AsAction;

    public function handle(Portfolio $portfolio, array $modelData)
    {
        $shopifyProductId = Arr::get($modelData, 'shopify_product_id');

        if (AdoptShopifyProductVariant::run($portfolio, $shopifyProductId) === null) {
            $refusal = LinkShopifyPortfolio::refusal($portfolio->customerSalesChannel, $shopifyProductId, null, $portfolio);

            if ($refusal === null) {
                $replacedVariantOwner = StoreShopifyProductVariant::ownerOfStandaloneVariantThatWouldBeReplaced($portfolio, $shopifyProductId);
                $refusal              = $replacedVariantOwner === null ? null : StoreShopifyProductVariant::replacedVariantMessage($replacedVariantOwner, false);
            }

            if ($refusal !== null) {
                UpdatePortfolio::run($portfolio, ['errors_response' => ['message' => $refusal]]);
                UploadProductToShopifyProgressEvent::dispatch($portfolio->customerSalesChannel->user, $portfolio->refresh());

                return;
            }

            if ($portfolio->isShopifyVariantAdopted()) {
                UnlinkRetinaPortfolio::run($portfolio);
            }

            LinkShopifyPortfolio::run($portfolio, $shopifyProductId);

            $portfolio->refresh();
            StoreShopifyProductVariant::run($portfolio, 0);
        }

        $portfolio = CheckShopifyPortfolio::run($portfolio->refresh());

        UploadProductToShopifyProgressEvent::dispatch($portfolio->customerSalesChannel->user, $portfolio);
    }


    public function rules(): array
    {
        return [
            'shopify_product_id' => ['required', 'string'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);
        $this->handle($portfolio, $this->validatedData);
    }

}
