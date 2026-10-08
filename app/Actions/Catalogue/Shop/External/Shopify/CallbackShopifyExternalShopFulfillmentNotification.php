<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;

class CallbackShopifyExternalShopFulfillmentNotification extends OrgAction
{
    public const array HANDLED_KINDS = ['FULFILLMENT_REQUEST', 'CANCELLATION_REQUEST'];

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string'],
        ];
    }

    /**
     * Shopify only says a request is waiting, so the shop is read right away by the same sync the schedule runs;
     * Shopify gives up on slow answers and retries anything that is not 2xx, so the work is queued.
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): Response
    {
        $shop = $shopifyUser->externalShop;

        if ($shopifyUser->trashed() || !$shop || $shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            return response()->noContent(200);
        }

        $this->initialisationFromShop($shop, $request);

        if (in_array($this->validatedData['kind'], self::HANDLED_KINDS, true)) {
            GetShopifyOrdersInShop::dispatch($shop);
        }

        return response()->noContent(200);
    }
}
