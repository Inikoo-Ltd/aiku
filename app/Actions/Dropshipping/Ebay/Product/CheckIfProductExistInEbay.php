<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CheckIfProductExistInEbay extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser, Portfolio $portfolio): array
    {
        try {
            if ($portfolio->platform_product_id) {
                $searchFields = [
                    'offerId' => $portfolio->platform_product_id
                ];
            } else {
                $searchFields = [
                    'sku' => $portfolio->sku
                ];
            }

            $result = [];

            foreach ($searchFields as $field => $value) {

                if ($field === 'sku') {
                    $searchResult = $ebayUser->getOffers($searchFields);
                } else {
                    $searchResult = $ebayUser->getOffer($value);
                }

                if (!empty($searchResult)) {
                    $result = $searchResult;
                    break;
                }
            }

            return self::publishedOffer($result);
        } catch (\Exception $e) {
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());

            return [];
        }
    }

    /**
     * The offer list endpoint wraps results in {offers: [...], total: n}; the single offer endpoint returns the offer itself.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function publishedOffer(array $result): array
    {
        if (Arr::has($result, 'error')) {
            return [];
        }

        $offers = Arr::has($result, 'offers') ? Arr::get($result, 'offers', []) : [$result];

        foreach ($offers as $offer) {
            if (is_array($offer) && Arr::get($offer, 'status') === 'PUBLISHED') {
                return $offer;
            }
        }

        return [];
    }
}
