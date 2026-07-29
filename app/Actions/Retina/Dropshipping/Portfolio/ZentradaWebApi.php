<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZentradaWebApi extends RetinaAction
{
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

        $query = Product::query()
            ->leftJoin('model_has_brands', function ($join) {
                $join->on('model_has_brands.model_id', '=', 'products.id')
                    ->where('model_has_brands.model_type', '=', 'Product');
            })
            ->leftJoin('brands', 'model_has_brands.brand_id', '=', 'brands.id')
            ->where('products.shop_id', $shop->id)
            ->where('products.is_for_sale', true)
            ->where('products.price', '>', 0)
            ->select([
                'products.code',
                'products.name',
                'products.barcode',
                'products.description',
                'products.price',
                'products.rrp',
                'products.units',
                'products.available_quantity',
                'products.marketing_weight',
                'products.tariff_code',
                'products.country_of_origin',
                'products.web_images',
                'brands.name as brand_name',
            ])
            ->orderBy('products.id');

        return response()->streamDownload(function () use ($query, $currency) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::COLUMNS, ';', '"', '');

            $query->chunk(200, function ($rows) use ($handle, $currency) {
                foreach ($rows as $row) {
                    fputcsv($handle, $this->mapRow($row, $currency), ';', '"', '');
                }
            });

            fclose($handle);
        }, 'items.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    private function mapRow(Product $row, ?string $currency): array
    {
        $unitsPerPackage  = $row->units > 0 ? $row->units : 1;
        $images           = $this->extractImages($row->web_images);
        $shortDescription = $this->shortDescription($row->description) ?: $row->name;

        return [
            'market_place_allocation'                 => 'EU',
            'item_number'                             => $row->code,
            'ean_code'                                => $row->barcode,
            'ean_ve'                                  => '',
            'product_description'                     => $row->name,
            'short_product_description'               => $shortDescription,
            'detailed_product_description'            => '',
            'brand_name'                              => $row->brand_name ?? '',
            'image'                                   => $images[0] ?? '',
            'image2'                                  => $images[1] ?? '',
            'image3'                                  => $images[2] ?? '',
            'currency'                                => $currency,
            'VAT'                                     => '23',
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
            'weight'                                  => $row->marketing_weight,
            'collection'                              => '',
            'statistical_number'                      => $row->tariff_code,
            'country_of_origin'                       => $row->country_of_origin,
            'dangerous_goods'                         => '',
            'energyEfficiency'                        => '',
            'energyEffImage'                          => '',
        ];
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

            if (count($images) === 3) {
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
