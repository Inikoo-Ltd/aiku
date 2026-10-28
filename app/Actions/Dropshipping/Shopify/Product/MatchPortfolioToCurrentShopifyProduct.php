<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Traits\Authorisations\WithCustomerSalesChannelEditAuthorisation;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Portfolio\UnlinkRetinaPortfolio;
use App\Events\UploadProductToShopifyProgressEvent;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchPortfolioToCurrentShopifyProduct extends OrgAction
{
    use WithCustomerSalesChannelEditAuthorisation;
    use AsAction;

    /**
     * @return array{0: bool, 1: string}
     */
    public function handle(Portfolio $portfolio, array $modelData): array
    {
        $shopifyProductId = Arr::get($modelData, 'shopify_product_id');

        $result = AdoptShopifyProductVariant::run($portfolio, $shopifyProductId, Arr::has($modelData, 'manage_price') ? (bool) Arr::get($modelData, 'manage_price') : null);

        if ($result === null) {
            $refusal = LinkShopifyPortfolio::refusal($portfolio->customerSalesChannel, $shopifyProductId, null, $portfolio);

            if ($refusal === null) {
                $replacedVariantOwner = StoreShopifyProductVariant::ownerOfStandaloneVariantThatWouldBeReplaced($portfolio, $shopifyProductId);
                $refusal              = $replacedVariantOwner === null ? null : StoreShopifyProductVariant::replacedVariantMessage($replacedVariantOwner, false);
            }

            if ($refusal !== null) {
                UpdatePortfolio::run($portfolio, ['errors_response' => ['message' => $refusal]]);
                UploadProductToShopifyProgressEvent::dispatch($portfolio->customerSalesChannel->user, $portfolio->refresh());

                return [false, $refusal];
            }

            if ($portfolio->isShopifyVariantAdopted()) {
                UnlinkRetinaPortfolio::run($portfolio);
            }

            $previousProductId = $portfolio->platform_product_id;

            LinkShopifyPortfolio::run($portfolio, $shopifyProductId);

            $portfolio->refresh();
            $result = StoreShopifyProductVariant::run($portfolio, 0);

            if (!$result[0] && $portfolio->refresh()->platform_product_id === $shopifyProductId) {
                UpdatePortfolio::run($portfolio, ['platform_product_id' => $previousProductId]);
            }
        }

        $portfolio = CheckShopifyPortfolio::run($portfolio->refresh());

        UploadProductToShopifyProgressEvent::dispatch($portfolio->customerSalesChannel->user, $portfolio);

        return $result;
    }

    /**
     * @throws ValidationException
     */
    public static function throwIfRefused(array $result): void
    {
        [$matched, $message] = $result;

        if (!$matched) {
            throw ValidationException::withMessages(['message' => $message]);
        }
    }


    public function rules(): array
    {
        return [
            'shopify_product_id' => ['required', 'string'],
            'manage_price'       => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);
        self::throwIfRefused($this->handle($portfolio, $this->validatedData));
    }

}
