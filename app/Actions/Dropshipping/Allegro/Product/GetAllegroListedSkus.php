<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Models\Dropshipping\AllegroUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAllegroListedSkus
{
    use AsAction;

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 59;

    /**
     * Allegro carries the seller's own reference on the offer as external.id, which is the
     * only SKU-shaped field its offers expose.
     *
     * @return array<string, string> lowercased sku => allegro offer id
     */
    public function handle(AllegroUser $allegroUser): array
    {
        $listedSkus = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $allegroUser->getOffers([
                'limit'  => self::PAGE_SIZE,
                'offset' => $page * self::PAGE_SIZE
            ]);

            $offers = Arr::get($response, 'offers');

            if (!is_array($offers)) {
                return $listedSkus;
            }

            foreach ($offers as $offer) {
                $externalId = Arr::get($offer, 'external.id');
                $offerId    = Arr::get($offer, 'id');

                if ($externalId && $offerId) {
                    $listedSkus[Str::lower($externalId)] = (string) $offerId;
                }
            }

            if (count($offers) < self::PAGE_SIZE) {
                return $listedSkus;
            }
        }

        Log::warning('Allegro offers too many to read in full for bulk matching', [
            'allegro_user_id' => $allegroUser->id,
            'skus_read'       => count($listedSkus)
        ]);

        return $listedSkus;
    }
}
