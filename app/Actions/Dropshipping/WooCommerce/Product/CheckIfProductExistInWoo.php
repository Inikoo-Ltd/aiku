<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CheckIfProductExistInWoo extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    /**
     * An error reply comes back as a list holding the raw body, and a missing product as
     * {code: woocommerce_rest_product_invalid_id}. Neither is a product, only entries with an id are.
     *
     * @return array<int, array>
     */
    public static function onlyProducts(?array $result): array
    {
        if (!$result || !array_is_list($result)) {
            return [];
        }

        return array_values(array_filter(
            $result,
            fn ($product) => is_array($product) && Arr::get($product, 'id')
        ));
    }

    /**
     * True only when the store itself says the product does not exist. An error page, a timeout
     * or a blocked call is not an answer and must not be read as "gone".
     */
    public static function isMissingProductReply(?array $reply): bool
    {
        $body = Arr::get($reply, '0');

        if (!is_string($body)) {
            return false;
        }

        $error = json_decode($body, true);

        return Arr::get($error, 'code') === 'woocommerce_rest_product_invalid_id'
            || Arr::get($error, 'data.status') === 404;
    }

    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio): array
    {
        try {
            if ($portfolio->platform_product_id) {
                return self::onlyProducts([$wooCommerceUser->getWooCommerceProduct($portfolio->platform_product_id)]);
            }

            return self::possibleMatches($wooCommerceUser, $portfolio);
        } catch (\Exception $e) {
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Listed products the portfolio could be matched with, by sku first, then slug, then name.
     *
     * @return array<int, array>
     */
    public static function possibleMatches(WooCommerceUser $wooCommerceUser, Portfolio $portfolio): array
    {
        $searchFields = [
            'sku'    => $portfolio->sku,
            'slug'   => $portfolio->platform_handle,
            'search' => $portfolio->item_name
        ];

        foreach ($searchFields as $field => $value) {
            if (blank($value)) {
                continue;
            }

            $searchResult = self::onlyProducts($wooCommerceUser->getWooCommerceProducts([
                $field => $value
            ]));

            if (!empty($searchResult)) {
                return $searchResult;
            }
        }

        return [];
    }
}
