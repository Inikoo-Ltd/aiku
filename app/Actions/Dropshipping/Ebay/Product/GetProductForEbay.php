<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetProductForEbay
{
    use AsAction;
    use WithAttributes;

    public $commandSignature = 'dropshipping:ebay:product:get {customerSalesChannel}';

    private const int INVENTORY_PAGE_SIZE = 100;

    private const int MAX_INVENTORY_PAGES = 20;

    private const int CACHE_TTL_SECONDS = 300;

    /**
     * What the seller can be matched against is their offers, not their inventory:
     * an inventory item without an offer has never been listed and matching to it
     * leaves the portfolio pointing at nothing.
     *
     * eBay cannot search or filter this list itself, so the offers are read once,
     * held briefly, and then paged and searched here.
     */
    public function handle(?EbayUser $ebayUser, $query = '', $offset = 0, $limit = 50): array
    {
        if (!$ebayUser) {
            return [];
        }

        $listings = $this->getListedOffers($ebayUser);

        if (filled($query)) {
            $listings = $this->filterByQuery($listings, $query);
        }

        return array_slice($listings, max(0, (int) $offset), max(1, (int) $limit));
    }

    /**
     * @return array<int, array>
     */
    private function getListedOffers(EbayUser $ebayUser): array
    {
        return Cache::remember(
            'ebay-listed-offers-'.$ebayUser->id,
            self::CACHE_TTL_SECONDS,
            fn () => $this->fetchListedOffers($ebayUser)
        );
    }

    /**
     * @return array<int, array>
     */
    private function fetchListedOffers(EbayUser $ebayUser): array
    {
        $inventoryItems = $this->fetchInventoryItems($ebayUser);

        $skus = array_filter(array_map(fn ($item) => Arr::get($item, 'sku'), $inventoryItems));

        $offersBySku = $ebayUser->getOffersForSkus($skus);

        $listings = [];

        foreach ($inventoryItems as $inventoryItem) {
            $offers = Arr::get($offersBySku, (string) Arr::get($inventoryItem, 'sku'));

            if (blank($offers)) {
                continue;
            }

            $listings[] = $this->transformToStandardFormat($inventoryItem, Arr::first($offers));
        }

        return $listings;
    }

    /**
     * @return array<int, array>
     */
    private function fetchInventoryItems(EbayUser $ebayUser): array
    {
        $inventoryItems = [];

        for ($page = 0; $page < self::MAX_INVENTORY_PAGES; $page++) {
            $response = $ebayUser->getProducts(self::INVENTORY_PAGE_SIZE, $page * self::INVENTORY_PAGE_SIZE);

            if (Arr::has($response, 'error') || Arr::has($response, 'errors')) {
                return $inventoryItems;
            }

            $pageItems      = Arr::get($response, 'inventoryItems', []);
            $inventoryItems = array_merge($inventoryItems, $pageItems);

            if (count($pageItems) < self::INVENTORY_PAGE_SIZE) {
                return $inventoryItems;
            }
        }

        Log::warning('eBay inventory too large to read in full for the product picker', [
            'ebay_user_id' => $ebayUser->id,
            'items_read'   => count($inventoryItems)
        ]);

        return $inventoryItems;
    }

    /**
     * @param  array<int, array>  $listings
     * @return array<int, array>
     */
    private function filterByQuery(array $listings, string $query): array
    {
        $needle = Str::lower(trim($query));

        return array_values(array_filter(
            $listings,
            fn ($listing) => Str::contains(Str::lower((string) Arr::get($listing, 'name')), $needle)
                || Str::contains(Str::lower((string) Arr::get($listing, 'code')), $needle)
        ));
    }

    /**
     * The SKU stays the id: matching an offer is done by SKU, not by offer id.
     */
    private function transformToStandardFormat($inventoryItem, $offer): array
    {
        $sku = Arr::get($inventoryItem, 'sku');

        return [
            'id'            => $sku,
            'name'          => Arr::get($inventoryItem, 'product.title') ?: $sku,
            'slug'          => $sku,
            'code'          => $sku,
            'offer_id'      => Arr::get($offer, 'offerId'),
            'listing_id'    => Arr::get($offer, 'listing.listingId'),
            'price'         => Arr::get($offer, 'pricingSummary.price.value'),
            'currency_code' => Arr::get($offer, 'pricingSummary.price.currency'),
            'status'        => Arr::get($offer, 'status'),
            'images'        => [
                [
                    'src' => Arr::get($inventoryItem, 'product.imageUrls.0')
                ]
            ]
        ];
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): array
    {
        return $this->handle($ebayUser);
    }

    public function asCommand(Command $command): array
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        return $this->handle($customerSalesChannel->user);
    }
}
