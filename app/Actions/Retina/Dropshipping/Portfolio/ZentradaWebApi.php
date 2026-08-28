<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use App\Helpers\NaturalLanguage;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\TaxCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZentradaWebApi extends RetinaAction
{
    private const int MAX_IMAGES = 10;

    private const array COLUMNS = [
        'market_place_allocation',
        'item_number',
        'ean_code',
        'ean_ve',
        'product_description',
        'short_product_description',
        'detailed_product_description',
        'brand_name',
        'image',
        'image2',
        'image3',
        'image4',
        'image5',
        'image6',
        'image7',
        'image8',
        'image9',
        'image10',
        'currency',
        'VAT',
        'quantity_of_units_per_package',
        'minimum_order_quantity_in_packing_units',
        'net_price_per_unit',
        'promotion_discount',
        'volumedbasedpricing_quantity1',
        'volumebasedpricing_price1',
        'volumedbasedpricing_quantity2',
        'volumedbasedpricing_quantity3',
        'available_quantity_in_packing_units',
        'recommended_retail_price',
        'activ_until',
        'weight',
        'collection',
        'statistical_number',
        'country_of_origin',
        'dangerous_goods',
        'energyEfficiency',
        'energyEffImage',
    ];

    public function handle(Shop $shop): StreamedResponse
    {
        $currency = $shop->currency?->code;
        $vat      = $this->getVatRate($shop);

        $query = Product::query()
            ->where('products.shop_id', $shop->id)
            ->where('products.is_for_sale', true)
            ->where('products.price', '>', 0)
            ->with([
                'brands:brands.id,brands.name',
                'tradeUnits:trade_units.id,trade_units.code,trade_units.barcode,trade_units.tariff_code',
                'tradeUnits.brands:brands.id,brands.name',
            ])
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.barcode',
                'products.description',
                'products.price',
                'products.rrp',
                'products.units',
                'products.available_quantity',
                'products.marketing_weight',
                'products.gross_weight',
                'products.marketing_dimensions',
                'products.marketing_ingredients',
                'products.tariff_code',
                'products.country_of_origin',
                'products.web_images',
            ])
            ->orderBy('products.id');

        return response()->streamDownload(function () use ($query, $currency, $vat) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::COLUMNS, ';', '"', '');

            $query->chunk(200, function ($rows) use ($handle, $currency, $vat) {
                foreach ($rows as $row) {
                    fputcsv($handle, $this->mapRow($row, $currency, $vat), ';', '"', '');
                }
            });

            fclose($handle);
        }, 'items.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    private function mapRow(Product $row, ?string $currency, string $vat): array
    {
        $unitsPerPackage  = $row->units > 0 ? $row->units : 1;
        $images           = $this->extractImages($row->web_images);
        $shortDescription = $this->shortDescription($row->description) ?: $row->name;
        $mainTradeUnit    = $this->getMainTradeUnit($row);

        return [
            'market_place_allocation'                 => 'EU',
            'item_number'                             => $row->code,
            'ean_code'                                => $row->barcode ?: $mainTradeUnit?->barcode,
            'ean_ve'                                  => '',
            'product_description'                     => $row->name,
            'short_product_description'               => $shortDescription,
            'detailed_product_description'            => $this->detailedDescription($row, $shortDescription),
            'brand_name'                              => $this->getBrandName($row),
            'image'                                   => $images[0] ?? '',
            'image2'                                  => $images[1] ?? '',
            'image3'                                  => $images[2] ?? '',
            'image4'                                  => $images[3] ?? '',
            'image5'                                  => $images[4] ?? '',
            'image6'                                  => $images[5] ?? '',
            'image7'                                  => $images[6] ?? '',
            'image8'                                  => $images[7] ?? '',
            'image9'                                  => $images[8] ?? '',
            'image10'                                 => $images[9] ?? '',
            'currency'                                => $currency,
            'VAT'                                     => $vat,
            'quantity_of_units_per_package'           => $unitsPerPackage,
            'minimum_order_quantity_in_packing_units' => 1,
            'net_price_per_unit'                      => ($row->price ?? 0) / $unitsPerPackage,
            'promotion_discount'                      => '',
            'volumedbasedpricing_quantity1'           => '',
            'volumebasedpricing_price1'               => '',
            'volumedbasedpricing_quantity2'           => '',
            'volumedbasedpricing_quantity3'           => '',
            'available_quantity_in_packing_units'     => floor($row->available_quantity ?? 0),
            'recommended_retail_price'                => ($row->rrp ?? 0) / $unitsPerPackage,
            'activ_until'                             => '',
            'weight'                                  => number_format($this->weightPerUnit($row, $unitsPerPackage) / 1000, 2),
            'collection'                              => '',
            'statistical_number'                      => $this->getStatisticalNumber($row, $mainTradeUnit),
            'country_of_origin'                       => $row->country_of_origin,
            'dangerous_goods'                         => '',
            'energyEfficiency'                        => '',
            'energyEffImage'                          => '',
        ];
    }

    /**
     * Zentrada matches an article by its EAN, so a component's code must never be published as the
     * whole article's. Composed products keep the code of the trade unit they are built around, e.g.
     * the product RTL-02 (lamp plus a UK plug) is built around the trade unit RTL-02, so that trade
     * unit alone carries the article's EAN and tariff code.
     */
    private function getMainTradeUnit(Product $product): ?TradeUnit
    {
        if ($product->tradeUnits->count() === 1) {
            return $product->tradeUnits->first();
        }

        $tradeUnitsSharingCode = $product->tradeUnits->filter(
            fn (TradeUnit $tradeUnit) => $tradeUnit->code !== ''
                && str_starts_with(strtoupper($product->code), strtoupper($tradeUnit->code))
        );

        return $tradeUnitsSharingCode->count() === 1 ? $tradeUnitsSharingCode->first() : null;
    }

    /**
     * A composed product carries the tariff code of every part it is made of, which cannot be
     * declared as one statistical number. The code of the trade unit the product is built around
     * replaces that list, and nothing is sent when no single code can be established: a wrong
     * statistical number is a customs declaration error, an empty one is not.
     */
    private function getStatisticalNumber(Product $product, ?TradeUnit $mainTradeUnit): string
    {
        $productTariffCodes = $this->splitTariffCodes($product->tariff_code);

        if (count($productTariffCodes) === 1) {
            return $productTariffCodes[0];
        }

        return $this->splitTariffCodes($mainTradeUnit?->tariff_code)[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function splitTariffCodes(?string $tariffCodes): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $tariffCodes))));
    }

    private function getBrandName(Product $product): string
    {
        $brand = $product->brands->first();

        if (!$brand) {
            $brand = $product->tradeUnits->pluck('brands')->flatten()->first();
        }

        return $brand?->name ?? '';
    }

    /**
     * Prices are per unit, so the weight has to be per unit too. The marketing weight already is,
     * the gross weight covers the whole packing unit including its packing.
     */
    private function weightPerUnit(Product $product, float $unitsPerPackage): float
    {
        if ($product->marketing_weight) {
            return (float) $product->marketing_weight;
        }

        return ($product->gross_weight ?? 0) / $unitsPerPackage;
    }

    private function detailedDescription(Product $product, string $shortDescription): string
    {
        $specifications = array_filter([
            'Weight'          => NaturalLanguage::make()->weight($product->marketing_weight),
            'Shipping weight' => NaturalLanguage::make()->weight($product->gross_weight),
            'Dimensions'      => NaturalLanguage::make()->dimensions($product->marketing_dimensions),
            'Material'        => trim((string) $product->marketing_ingredients),
        ]);

        $lines = [$shortDescription];

        foreach ($specifications as $label => $value) {
            $lines[] = $label.': '.$value;
        }

        return implode('<br />', array_filter($lines));
    }

    private function getVatRate(Shop $shop): string
    {
        $taxCategory = TaxCategory::where('type', TaxCategoryTypeEnum::STANDARD)
            ->where('country_id', $shop->country_id)
            ->where('status', true)
            ->orderByDesc('id')
            ->first();

        if (!$taxCategory) {
            return '';
        }

        return rtrim(rtrim(number_format($taxCategory->rate * 100, 2, '.', ''), '0'), '.');
    }

    private function extractImages(mixed $webImages): array
    {
        if (is_string($webImages)) {
            $webImages = json_decode($webImages, true);
        }

        $images = [];
        foreach (Arr::get($webImages, 'all', []) as $image) {
            $url = Arr::get($image, 'gallery.original')
                ?? Arr::get($image, 'original.original')
                ?? Arr::get($image, 'thumbnail.original');

            if ($url) {
                $images[] = $url;
            }

            if (count($images) === self::MAX_IMAGES) {
                break;
            }
        }

        return $images;
    }

    private function shortDescription(?string $description): string
    {
        $description = trim(preg_replace('/\s\s+/', ' ', (string)$description));
        $description = str_replace(["\r\n", "\n"], ' ', $description);
        $description = preg_replace('/<p[^>]*?>/', '', $description);

        return str_replace('</p>', '<br />', $description);
    }

    public function asController(ActionRequest $request): StreamedResponse
    {
        $this->shop = $request->website->shop;

        return $this->handle($this->shop);
    }
}
