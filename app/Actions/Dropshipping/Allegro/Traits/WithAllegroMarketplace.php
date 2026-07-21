<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Traits;

use Illuminate\Support\Arr;

trait WithAllegroMarketplace
{
    /**
     * @return array<string, array{language: string, currency_code: string|null, price_decimals: int}>
     */
    protected function allegroMarketplaces(): array
    {
        return [
            'allegro-pl' => ['language' => 'pl-PL', 'currency_code' => 'PLN', 'price_decimals' => 2],
            'allegro-cz' => ['language' => 'cs-CZ', 'currency_code' => 'CZK', 'price_decimals' => 2],
            'allegro-sk' => ['language' => 'sk-SK', 'currency_code' => 'EUR', 'price_decimals' => 2],
            'allegro-hu' => ['language' => 'hu-HU', 'currency_code' => 'HUF', 'price_decimals' => 0],
        ];
    }

    /**
     * @return array{language: string, currency_code: string|null, price_decimals: int}
     */
    public function getAllegroMarketplace(?string $marketplaceId): array
    {
        return Arr::get(
            $this->allegroMarketplaces(),
            (string) $marketplaceId,
            ['language' => 'en-US', 'currency_code' => null, 'price_decimals' => 2]
        );
    }

    public function getAllegroOfferLanguage(?string $marketplaceId): string
    {
        return $this->getAllegroMarketplace($marketplaceId)['language'];
    }

    public function getAllegroCurrencyCode(?string $marketplaceId): ?string
    {
        return $this->getAllegroMarketplace($marketplaceId)['currency_code'];
    }

    /**
     * Allegro rejects an offer with "Number of decimal places is incorrect for the specified
     * market." when the amount carries more decimals than the market allows. Notably the
     * Hungarian market prices in whole HUF, so 2 decimals are invalid there.
     */
    public function formatAllegroPrice(float|int|string $price, ?string $marketplaceId): string
    {
        return number_format(
            (float) $price,
            $this->getAllegroMarketplace($marketplaceId)['price_decimals'],
            '.',
            ''
        );
    }
}
