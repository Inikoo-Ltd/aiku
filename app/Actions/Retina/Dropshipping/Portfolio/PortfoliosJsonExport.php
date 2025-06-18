<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class PortfoliosJsonExport
{
    use AsAction;

    public function handle(Customer $customer, CustomerSalesChannel $customerSalesChannel): array
    {

        return [
            'schema' => $this->schema(),
            'data' => $this->getData($customer, $customerSalesChannel),
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

    private function getData(Customer $customer, CustomerSalesChannel $customerSalesChannel): array
    {
        $portfolios = $customer->portfolios()
            ->where('customer_sales_channel', $customerSalesChannel->id)

            ->with(['item.family', 'item.currency'])
            ->with(['item.image'])
            ->get();

        return $portfolios->map(function ($row) {
            $webpage = Webpage::where('model_id', $row->item_id)->where('model_type', $row->item_type)->first();

            return [
                $row->status,
                $row->item_code,
                $row->reference,
                $row->item?->family?->name,
                $row->item?->barcode,
                '', // TODO: CPNP number
                '', // TODO: need add column for total price in portfolio
                $row->item?->units,
                $row->item?->unit,
                $row->item?->price,
                $row->item_name,
                '', // TODO: unit RRP
                '', // TODO: unit net weight
                '', // TODO: package weight (shipping)
                '', // TODO: unit dimensions
                '', // TODO: materials/ingredients
                '', // TODO: webpage description (html)
                $webpage?->description, // webpage description (plain text)
                $row->item?->currency?->code,
                '', // TODO: tariff code
                '', // TODO: duty rate
                '', // TODO: HTS US
                $row->item?->available_quantity,
                $row->item->image ? GetImgProxyUrl::run($row->item->image?->getImage()) : '',
                $row->item?->updated_at,
                '', // TODO: stock updated
                '', // TODO: price updated
                $row->item->images->sortByDesc('updated_at')->first()?->updated_at,
            ];
        })->toArray();
    }
}
