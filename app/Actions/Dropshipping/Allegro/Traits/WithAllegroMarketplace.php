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
     * @return array<string, array{language: string, currency_code: string|null, price_decimals: int, price_step: float|null}>
     */
    protected function allegroMarketplaces(): array
    {
        return [
            'allegro-pl' => ['language' => 'pl-PL', 'currency_code' => 'PLN', 'price_decimals' => 2, 'price_step' => null],
            'allegro-cz' => ['language' => 'cs-CZ', 'currency_code' => 'CZK', 'price_decimals' => 2, 'price_step' => null],
            'allegro-sk' => ['language' => 'sk-SK', 'currency_code' => 'EUR', 'price_decimals' => 2, 'price_step' => null],
            'allegro-hu' => ['language' => 'hu-HU', 'currency_code' => 'HUF', 'price_decimals' => 0, 'price_step' => 5.0],
        ];
    }

    /**
     * @return array{language: string, currency_code: string|null, price_decimals: int, price_step: float|null}
     */
    public function getAllegroMarketplace(?string $marketplaceId): array
    {
        return Arr::get(
            $this->allegroMarketplaces(),
            (string) $marketplaceId,
            ['language' => 'en-US', 'currency_code' => null, 'price_decimals' => 2, 'price_step' => null]
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
     * Each Allegro market constrains the shape of the amount, and rejects the offer otherwise:
     *
     * - "Number of decimal places is incorrect for the specified market." — the Hungarian
     *   market prices in whole HUF, so the 2 decimals the other markets expect are invalid.
     * - "The price in HUF must be divisible by 5 HUF (acceptable endings: 0 or 5)." — the
     *   Hungarian market also only accepts amounts on a 5 HUF step.
     *
     * The step is applied upwards so that a converted price is never rounded below the price
     * the customer set.
     */
    public function formatAllegroPrice(float|int|string $price, ?string $marketplaceId): string
    {
        $marketplace = $this->getAllegroMarketplace($marketplaceId);

        $price = (float) $price;

        if ($marketplace['price_step']) {
            $steps = ceil(round($price / $marketplace['price_step'], 6));
            $price = $steps * $marketplace['price_step'];
        }

        return number_format($price, $marketplace['price_decimals'], '.', '');
    }
}
