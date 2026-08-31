<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Dropshipping\WithPlatformListedProducts;
use App\Models\Dropshipping\AllegroUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SearchAllegroProducts
{
    use AsAction;
    use WithAttributes;
    use WithPlatformListedProducts;

    public $commandSignature = 'dropshipping:allegro:product:get {allegroUser}';

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 59;

    /**
     * The seller's own offers, not Allegro's global catalogue: a match has to point at an
     * offer id, and Allegro carries the seller's own reference on the offer as external.id.
     */
    public function handle(?AllegroUser $allegroUser, $query = '', $offset = 0, $limit = 50): array
    {
        if (!$allegroUser) {
            return [];
        }

        return $this->pickListedProducts(
            'allegro-listed-offers-'.$allegroUser->id,
            fn () => $this->fetchListedProducts($allegroUser),
            (string) $query,
            (int) $offset,
            (int) $limit
        );
    }

    /**
     * @return array<int, array>
     */
    private function fetchListedProducts(AllegroUser $allegroUser): array
    {
        $listedProducts = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $allegroUser->getOffers([
                'limit'  => self::PAGE_SIZE,
                'offset' => $page * self::PAGE_SIZE
            ]);

            $offers = Arr::get($response, 'offers');

            if (!is_array($offers)) {
                return $listedProducts;
            }

            foreach ($offers as $offer) {
                $listedProducts[] = $this->transformToStandardFormat($offer);
            }

            if (count($offers) < self::PAGE_SIZE) {
                return $listedProducts;
            }
        }

        Log::warning('Allegro offers too many to read in full for the product picker', [
            'allegro_user_id' => $allegroUser->id,
            'offers_read'     => count($listedProducts)
        ]);

        return $listedProducts;
    }

    private function transformToStandardFormat($offer): array
    {
        return [
            'id'            => (string) Arr::get($offer, 'id'),
            'name'          => Arr::get($offer, 'name'),
            'slug'          => Arr::get($offer, 'external.id'),
            'code'          => Arr::get($offer, 'external.id'),
            'price'         => Arr::get($offer, 'sellingMode.price.amount'),
            'currency_code' => Arr::get($offer, 'sellingMode.price.currency'),
            'status'        => Arr::get($offer, 'publication.status'),
            'images'        => [
                [
                    'src' => Arr::get($offer, 'primaryImage.url') ?? Arr::get($offer, 'images.0.url')
                ]
            ]
        ];
    }

    public function asController(AllegroUser $allegroUser, ActionRequest $request): array
    {
        return $this->handle($allegroUser);
    }
}
