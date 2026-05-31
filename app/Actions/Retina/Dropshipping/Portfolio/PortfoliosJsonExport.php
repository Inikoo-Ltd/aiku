<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class PortfoliosJsonExport
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        return [
            'schema' => $this->schema(),
            'data'   => $this->getData($customerSalesChannel),
        ];
    }

    public function schema(): array
    {
        return [
            ['field_code' => 'product_status', 'field_description' => 'Status'],
            ['field_code' => 'product_code', 'field_description' => 'Product code'],
            ['field_code' => 'product_user_reference', 'field_description' => 'Product user reference'],
            ['field_code' => 'family', 'field_description' => 'Family'],
            ['field_code' => 'product_barcode', 'field_description' => 'Barcode'],
            ['field_code' => 'product_cpnp', 'field_description' => 'CPNP number'],
            ['field_code' => 'product_price', 'field_description' => 'Price'],
            ['field_code' => 'units_per_outer', 'field_description' => 'Units per outer'],
            ['field_code' => 'product_unit_type', 'field_description' => 'Unit label'],
            ['field_code' => 'product_unit_price', 'field_description' => 'Unit price'],
            ['field_code' => 'product_unit_name', 'field_description' => 'Unit Name'],
            ['field_code' => 'product_unit_rrp', 'field_description' => 'Unit RRP'],
            ['field_code' => 'product_unit_weight', 'field_description' => 'Unit net weight'],
            ['field_code' => 'product_package_weight', 'field_description' => 'Package weight (shipping)'],
            ['field_code' => 'product_unit_dimensions', 'field_description' => 'Unit dimensions'],
            ['field_code' => 'product_materials', 'field_description' => 'Materials/Ingredients'],
            ['field_code' => 'webpage_product_description_html', 'field_description' => 'Webpage description (html)'],
            ['field_code' => 'webpage_product_description_text', 'field_description' => 'Webpage description (plain text)'],
            ['field_code' => 'product_origin_country', 'field_description' => 'Country of origin'],
            ['field_code' => 'product_tariff_code', 'field_description' => 'Tariff code'],
            ['field_code' => 'product_duty_rate', 'field_description' => 'Duty rate'],
            ['field_code' => 'product_hts_us', 'field_description' => 'HTS US'],
            ['field_code' => 'product_stock', 'field_description' => 'Stock'],
            ['field_code' => 'images', 'field_description' => 'Images'],
            ['field_code' => 'data_last_updated_datetime', 'field_description' => 'Data updated'],
            ['field_code' => 'stock_last_updated_datetime', 'field_description' => 'Stock updated'],
            ['field_code' => 'price_last_updated_datetime', 'field_description' => 'Price updated'],
            ['field_code' => 'images__updated_datetime', 'field_description' => 'Images updated'],
        ];
    }

    private function getData(CustomerSalesChannel $customerSalesChannel): array
    {
        $portfolios = DB::table('portfolios')
            ->select(
                'portfolios.status',
                'portfolios.item_code',
                'portfolios.reference',
                'products.units',
                'products.unit',
                'products.price',
                'products.barcode',
                'products.cpnp_number',
                'products.name',
                'products.rrp',
                'products.marketing_weight',
                'products.gross_weight',
                'products.description',
                'products.country_of_origin',
                'products.tariff_code',
                'products.duty_rate',
                'products.hts_us',
                'products.available_quantity',
                'products.updated_at',
                'products.available_quantity_updated_at',
                'products.price_updated_at',
                'products.available_quantity_updated_at',
                'products.images_updated_at',
                'products.web_images',
                'products.marketing_dimensions',
                'products.marketing_ingredients',
                'product_categories.name as family_name',
            )
            ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.family_id', '=', 'product_categories.id')
            ->where('portfolios.item_type', 'Product')
            ->where('portfolios.customer_sales_channel_id', $customerSalesChannel->id)
            ->get();

        return $portfolios->map(function ($row) {
            if ($row->units != 0) {
                $unitPrice = $row->price / $row->units;
            } else {
                $unitPrice = 0;
            }

            return [
                $row->status,
                $row->item_code,
                $row->reference,
                $row->family_name,
                $row->barcode,
                $row->cpnp_number,
                $row->price,
                $row->units,
                $row->unit,
                $unitPrice,
                $row->name,
                $row->rrp,
                $row->marketing_weight,
                $row->gross_weight,
                $row->marketing_dimensions,
                $row->marketing_ingredients,
                $row->description,
                Str::of($row->description)->stripTags(),
                $row->country_of_origin,
                $row->tariff_code,
                $row->duty_rate,
                $row->hts_us,
                $row->available_quantity,
                Arr::get(json_decode($row->web_images, true), 'main.gallery'),
                $row->updated_at,
                $row->available_quantity_updated_at,
                $row->price_updated_at,
                $row->images_updated_at,
            ];
        })->toArray();
    }
}
