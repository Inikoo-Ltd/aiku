<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-14h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\WooCommerce\Webhook;

use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\CheckIfProductExistInWoo;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteProductWebhooksWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * The webhook carries no signature aiku can verify, so a product is only dropped from the
     * portfolio once the store itself confirms it is gone or in the bin.
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $modelData): void
    {
        $productId = Arr::get($modelData, 'id');

        if (!$productId) {
            return;
        }

        $portfolio = Portfolio::where('customer_sales_channel_id', $wooCommerceUser->customer_sales_channel_id)
            ->where('platform_product_id', $productId)
            ->first();

        if ($portfolio && self::storeConfirmsProductGone($wooCommerceUser, (int) $productId)) {
            DeletePortfolio::run($portfolio, true);
        }
    }

    public static function storeConfirmsProductGone(WooCommerceUser $wooCommerceUser, int $productId): bool
    {
        $reply = $wooCommerceUser->getWooCommerceProduct($productId);

        if (is_array($reply) && Arr::has($reply, 'id')) {
            return Arr::get($reply, 'status') === 'trash';
        }

        return CheckIfProductExistInWoo::isMissingProductReply($reply);
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request): void
    {
        $this->initialisation($wooCommerceUser->organisation, $request);
        $this->handle($wooCommerceUser, $request->all());
    }
}
