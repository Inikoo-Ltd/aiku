<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2025 22:16:49 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Portfolio\UnlinkRetinaPortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallbackProductDelete extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * Not the 'shopify' queue: a merchant deleting thousands of products would queue them in front
     * of every other store's stock pushes and order fetches.
     */
    public string $jobQueue = 'shopify-bulk';

    public bool $jobDeleteWhenMissingModels = true;

    /**
     * We store Shopify products by their full id ("gid://shopify/Product/123"); the webhook body
     * carries the plain number. Unlinking keeps the product in My Products, the customer decides.
     */
    public function handle(ShopifyUser $shopifyUser, string $productGid): int
    {
        $portfolios = Portfolio::where('customer_sales_channel_id', $shopifyUser->customer_sales_channel_id)
            ->where('platform_product_id', $productGid)
            ->get();

        foreach ($portfolios as $portfolio) {
            UnlinkRetinaPortfolio::run($portfolio);
        }

        return $portfolios->count();
    }

    public function rules(): array
    {
        return [
            'id'                   => ['required', 'integer', 'min:1'],
            'admin_graphql_api_id' => ['sometimes', 'nullable', 'string', 'starts_with:gid://shopify/Product/'],
        ];
    }

    /**
     * Shopify retries any answer that is not 2xx and gives up on slow ones, so the unlink is
     * queued and a store we no longer serve still gets a 200: there is nothing for it to retry.
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): Response
    {
        if ($shopifyUser->trashed() || !$shopifyUser->customer_id || !$shopifyUser->customer_sales_channel_id) {
            return response()->noContent(200);
        }

        $this->initialisation($shopifyUser->organisation, $request);

        $productGid = $this->validatedData['admin_graphql_api_id'] ?? 'gid://shopify/Product/'.$this->validatedData['id'];

        static::dispatch($shopifyUser, $productGid);

        return response()->noContent(200);
    }
}
