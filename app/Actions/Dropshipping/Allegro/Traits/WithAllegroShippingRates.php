<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Traits;

use App\Actions\Dropshipping\Allegro\User\ProcessShippingRates;
use App\Models\Dropshipping\AllegroUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait WithAllegroShippingRates
{
    /**
     * Our own shipping price lists are named with this prefix, which is how we tell them
     * apart from the ones the seller already had on their account.
     */
    public const string ALLEGRO_SHIPPING_RATES_PREFIX = 'AW-EU-';

    public function allegroShippingRatesName(AllegroUser $allegroUser, string $countryCode): string
    {
        return self::ALLEGRO_SHIPPING_RATES_PREFIX . $allegroUser->customerSalesChannel->slug . '-' . $countryCode;
    }

    public function isAllegroShippingRatesOurs(?string $name): bool
    {
        return Str::startsWith((string) $name, self::ALLEGRO_SHIPPING_RATES_PREFIX);
    }

    /**
     * The id of the seller's shipping price list we should put our offers on.
     *
     * Only ever one of ours: taking whichever list the account happened to return first put
     * every offer we created on the seller's own pre-existing list, at their own prices, for
     * a parcel they do not send.
     */
    public function findOurAllegroShippingRatesId(AllegroUser $allegroUser): ?string
    {
        $shippingRates = collect(Arr::get($allegroUser->getShippingRates(), 'shippingRates', []));

        $ours = $shippingRates->first(fn ($rates) => $this->isAllegroShippingRatesOurs(Arr::get($rates, 'name')));

        return Arr::get($ours, 'id');
    }

    /**
     * Our price list id for the seller, creating the list on Allegro when they have none.
     *
     * @return array{id: string|null, message: string|null}
     */
    public function resolveAllegroShippingRates(AllegroUser $allegroUser, ?string $marketplaceId = null): array
    {
        if ($shippingId = $this->findOurAllegroShippingRatesId($allegroUser)) {
            return ['id' => $shippingId, 'message' => null];
        }

        $created = ProcessShippingRates::run($allegroUser, $marketplaceId);

        return [
            'id'      => Arr::get($created, 'id'),
            'message' => Arr::get($created, 'id') ? null : Arr::get($created, 'message', 'Allegro returned no shipping price list id.'),
        ];
    }

    /**
     * Attaches our price list to a channel saved without one, so an upload repairs the
     * channel instead of failing with Allegro's "No shipping price list set.".
     */
    public function ensureAllegroShippingRatesId(AllegroUser $allegroUser): ?string
    {
        if ($shippingId = Arr::get($allegroUser->settings, 'shipping.id')) {
            return $shippingId;
        }

        return Cache::lock('allegro-shipping-rates:'.$allegroUser->id, 60)->block(30, function () use ($allegroUser) {
            $allegroUser->refresh();

            if ($shippingId = Arr::get($allegroUser->settings, 'shipping.id')) {
                return $shippingId;
            }

            $shippingId = $this->resolveAllegroShippingRates($allegroUser)['id'];

            if ($shippingId) {
                $settings = $allegroUser->settings ?? [];
                data_set($settings, 'shipping.id', $shippingId);
                $data = $allegroUser->data ?? [];
                data_set($data, 'shipping_id', $shippingId);

                $allegroUser->update(['settings' => $settings, 'data' => $data]);
            }

            return $shippingId;
        });
    }
}
