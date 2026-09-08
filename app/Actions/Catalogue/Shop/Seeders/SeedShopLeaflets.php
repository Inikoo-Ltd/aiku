<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Catalogue\Shop\Seeders;

use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Billables\Leaflet;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The printed inserts a customer can add to a dropship order.
 *
 * A leaflet reaches a packaging through `family_codes`: the listing matches them with
 * `jsonb_exists(leaflets.family_codes, packagings.family_code)`, so an empty list means
 * the leaflet applies to nothing at all. Seeded leaflets are therefore attached to the
 * packaging families the shop actually has, which is why packagings are seeded first.
 *
 * Re-running is safe: rows are matched on shop + name and updated in place.
 */
class SeedShopLeaflets
{
    use AsAction;

    /**
     * @var array<int, array{name: string, type: LeafletTypeEnum, price: float}>
     */
    protected const CATALOGUE = [
        ['name' => 'Brand Story Leaflet', 'type' => LeafletTypeEnum::BRAND_STORY, 'price' => 0.00],
        ['name' => 'Thank You Card', 'type' => LeafletTypeEnum::THANK_YOU_CARD, 'price' => 0.05],
        ['name' => 'Discount Voucher', 'type' => LeafletTypeEnum::VOUCHER, 'price' => 0.03],
        ['name' => 'Product Care Instructions', 'type' => LeafletTypeEnum::CARE_INSTRUCTIONS, 'price' => 0.04],
        ['name' => 'Marketing Flyer', 'type' => LeafletTypeEnum::MARKETING_FLYER, 'price' => 0.06],
    ];

    /**
     * @return array<int, string> the packaging families the leaflets were attached to
     */
    public function handle(Shop $shop): array
    {
        $familyCodes = Packaging::where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('family_code')
            ->pluck('family_code')
            ->all();

        foreach (self::CATALOGUE as $leaflet) {
            Leaflet::withTrashed()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'name'    => $leaflet['name'],
                ],
                [
                    'group_id'        => $shop->group_id,
                    'organisation_id' => $shop->organisation_id,
                    'currency_id'     => $shop->currency_id,
                    'type'            => $leaflet['type'],
                    'price'           => $leaflet['price'],
                    'family_codes'    => $familyCodes,
                    'state'           => LeafletStateEnum::ACTIVE,
                    'deleted_at'      => null,
                    'data'            => [],
                ]
            );
        }

        return $familyCodes;
    }

    public string $commandSignature = 'shop:seed_leaflets {--shop= : Slug of a single shop, otherwise every shop}';

    public function asCommand(Command $command): int
    {
        $shops = $command->option('shop')
            ? Shop::where('slug', $command->option('shop'))->get()
            : Shop::all();

        if ($shops->isEmpty()) {
            $command->error('No shop found');

            return 1;
        }

        foreach ($shops as $shop) {
            $familyCodes = $this->handle($shop);

            if (!$familyCodes) {
                $command->warn("{$shop->slug}: leaflets seeded but attached to no packaging family — run shop:seed_packagings first, then this again");

                continue;
            }

            $command->info("Seeded leaflets for {$shop->slug} across ".count($familyCodes).' packaging families');
        }

        return 0;
    }
}
