<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Catalogue\Shop\Seeders;

use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Catalogue\Packaging\PackagingTypeEnum;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The packaging a customer can pick from on a dropship order. Sizes are grouped by
 * `family_code`, which is what the checkout shows as one card with a price range —
 * so a family needs several rows, not one.
 *
 * Re-running this is safe: rows are matched on shop + code and updated in place, which
 * keeps any order already pointing at a packaging id intact.
 */
class SeedShopPackagings
{
    use AsAction;

    /**
     * Dimensions are in millimetres, prices in the shop's own currency.
     *
     * @var array<int, array{family_code: string, family_name: string, type: PackagingTypeEnum, position: int, sizes: array<int, array{code: string, name: string, price: float, width: int|null, height: int|null}>}>
     */
    protected const CATALOGUE = [
        [
            'family_code' => 'pink-poly-bubble',
            'family_name' => 'Pink Poly Bubble Envelopes',
            'type'        => PackagingTypeEnum::STANDARD,
            'position'    => 1,
            'sizes'       => [
                ['code' => 'pink-bubble-a000', 'name' => 'Pink Poly Bubble Envelope A/000 (110 x 160mm)', 'price' => 0.11, 'width' => 110, 'height' => 160],
                ['code' => 'pink-bubble-c0', 'name' => 'Pink Poly Bubble Envelope C/0 (150 x 210mm)', 'price' => 0.18, 'width' => 150, 'height' => 210],
                ['code' => 'pink-bubble-d1', 'name' => 'Pink Poly Bubble Envelope D/1 (180 x 265mm)', 'price' => 0.26, 'width' => 180, 'height' => 265],
                ['code' => 'pink-bubble-f3', 'name' => 'Pink Poly Bubble Envelope F/3 (220 x 335mm)', 'price' => 0.38, 'width' => 220, 'height' => 335],
                ['code' => 'pink-bubble-h5', 'name' => 'Pink Poly Bubble Envelope H/5 (270 x 360mm)', 'price' => 0.55, 'width' => 270, 'height' => 360],
                ['code' => 'pink-bubble-k7', 'name' => 'Pink Poly Bubble Envelope K/7 (350 x 470mm)', 'price' => 0.84, 'width' => 350, 'height' => 470],
            ],
        ],
        [
            'family_code' => 'black-poly-bubble',
            'family_name' => 'Black Poly Bubble Envelopes',
            'type'        => PackagingTypeEnum::STANDARD,
            'position'    => 2,
            'sizes'       => [
                ['code' => 'black-bubble-c0', 'name' => 'Black Poly Bubble Envelope C/0 (150 x 210mm)', 'price' => 0.21, 'width' => 150, 'height' => 210],
                ['code' => 'black-bubble-d1', 'name' => 'Black Poly Bubble Envelope D/1 (180 x 265mm)', 'price' => 0.30, 'width' => 180, 'height' => 265],
                ['code' => 'black-bubble-f3', 'name' => 'Black Poly Bubble Envelope F/3 (220 x 335mm)', 'price' => 0.44, 'width' => 220, 'height' => 335],
                ['code' => 'black-bubble-h5', 'name' => 'Black Poly Bubble Envelope H/5 (270 x 360mm)', 'price' => 0.62, 'width' => 270, 'height' => 360],
                ['code' => 'black-bubble-k7', 'name' => 'Black Poly Bubble Envelope K/7 (350 x 470mm)', 'price' => 0.84, 'width' => 350, 'height' => 470],
            ],
        ],
        [
            'family_code' => 'green-poly-bubble',
            'family_name' => 'Green Poly Bubble Envelopes',
            'type'        => PackagingTypeEnum::STANDARD,
            'position'    => 3,
            'sizes'       => [
                ['code' => 'green-bubble-a000', 'name' => 'Green Poly Bubble Envelope A/000 (110 x 160mm)', 'price' => 0.11, 'width' => 110, 'height' => 160],
                ['code' => 'green-bubble-c0', 'name' => 'Green Poly Bubble Envelope C/0 (150 x 210mm)', 'price' => 0.17, 'width' => 150, 'height' => 210],
                ['code' => 'green-bubble-d1', 'name' => 'Green Poly Bubble Envelope D/1 (180 x 265mm)', 'price' => 0.24, 'width' => 180, 'height' => 265],
                ['code' => 'green-bubble-f3', 'name' => 'Green Poly Bubble Envelope F/3 (220 x 335mm)', 'price' => 0.35, 'width' => 220, 'height' => 335],
                ['code' => 'green-bubble-g4', 'name' => 'Green Poly Bubble Envelope G/4 (240 x 330mm)', 'price' => 0.47, 'width' => 240, 'height' => 330],
            ],
        ],
        [
            'family_code' => 'biodegradable-mailer',
            'family_name' => '100% Biodegradable Mailers',
            'type'        => PackagingTypeEnum::ECO,
            'position'    => 4,
            'sizes'       => [
                ['code' => 'bio-mailer-s', 'name' => 'Biodegradable Mailer S (170 x 230mm)', 'price' => 0.06, 'width' => 170, 'height' => 230],
                ['code' => 'bio-mailer-m', 'name' => 'Biodegradable Mailer M (250 x 350mm)', 'price' => 0.20, 'width' => 250, 'height' => 350],
                ['code' => 'bio-mailer-l', 'name' => 'Biodegradable Mailer L (350 x 450mm)', 'price' => 0.42, 'width' => 350, 'height' => 450],
                ['code' => 'bio-mailer-xl', 'name' => 'Biodegradable Mailer XL (450 x 550mm)', 'price' => 0.62, 'width' => 450, 'height' => 550],
                ['code' => 'bio-mailer-xxl', 'name' => 'Biodegradable Mailer XXL (600 x 700mm)', 'price' => 0.82, 'width' => 600, 'height' => 700],
            ],
        ],
        [
            // The fallback shown last: free, and deliberately without dimensions because
            // it is what gets used when an item fits none of the sized options.
            'family_code' => 'standard-packaging',
            'family_name' => 'Standard Packaging',
            'type'        => PackagingTypeEnum::STANDARD,
            'position'    => 5,
            'sizes'       => [
                ['code' => 'standard-packaging', 'name' => 'Standard Packaging', 'price' => 0, 'width' => null, 'height' => null],
            ],
        ],
    ];

    public function handle(Shop $shop): void
    {
        foreach (self::CATALOGUE as $family) {
            foreach ($family['sizes'] as $index => $size) {
                Packaging::withTrashed()->updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'code'    => $size['code'],
                    ],
                    [
                        'group_id'        => $shop->group_id,
                        'organisation_id' => $shop->organisation_id,
                        'currency_id'     => $shop->currency_id,
                        'family_code'     => $family['family_code'],
                        'name'            => $size['name'],
                        'type'            => $family['type'],
                        'price'           => $size['price'],
                        'width'           => $size['width'],
                        'height'          => $size['height'],
                        'state'           => PackagingStateEnum::ACTIVE,
                        // Families keep their order on the picker, sizes their order inside it.
                        'position'        => $family['position'] * 100 + $index,
                        'slug'            => Str::slug($shop->slug.'-'.$size['code']),
                        'deleted_at'      => null,
                        'data'            => ['family_name' => $family['family_name']],
                    ]
                );
            }
        }
    }

    public string $commandSignature = 'shop:seed_packagings {--shop= : Slug of a single shop, otherwise every shop}';

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
            $this->handle($shop);
            $command->info("Seeded packagings for {$shop->slug}");
        }

        return 0;
    }
}
