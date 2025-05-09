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
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PortfoliosCsvExport implements FromQuery, WithMapping, WithHeadings, ShouldQueue
{
    use AsAction;
    use Exportable;

    private Customer $customer;
    private Platform $platform;

    public function __construct(Customer $customer, Platform $platform)
    {
        $this->customer = $customer;
        $this->platform = $platform;
    }

    public function map($row): array
    {

        return [
            $row->status,
            $row->item_code,
            $row->reference,
            $row->item?->family?->name,
            $row->item?->barcode,
            '', // CPNP number
            $row->item?->price, // total price
            $row->item?->units,
            $row->item?->unit,
            $row->item?->price, // unit price
            $row->item_name,
            '', // unit RRP
            '', // unit net weight
            $row->item?->gross_weight,
            '', // unit dimensions
            '', // materials/ingredients
            '', // webpage description (html)
            '', // webpage description (plain text)
            $row->item?->currency->code, // country of origin
            '', // tariff code
            '', // duty rate
            '', // HTS US
            $row->item?->available_quantity,
            $row->item->image ? GetImgProxyUrl::run($row->item->image?->getImage()) : '', // images
            $row->item?->updated_at,
            '', // stock updated
            '', // price updated
            $row->item->images->sortByDesc('updated_at')->first()?->updated_at,
        ];
    }

    public function headings(): array
    {
        return [
            'Status',
            'Product code',
            'Product user reference',
            'Family',
            'Barcode',
            'CPNP number',
            'Price',
            'Units per outer',
            'Unit label',
            'Unit price',
            'Unit Name',
            'Unit RRP',
            'Unit net weight',
            'Package weight (shipping)',
            'Unit dimensions',
            'Materials/Ingredients',
            'Webpage description (html)',
            'Webpage description (plain text)',
            'Country of origin',
            'Tariff code',
            'Duty rate',
            'HTS US',
            'Stock',
            'Images',
            'Data updated',
            'Stock updated',
            'Price updated',
            'Images updated',
        ];
    }

    public function query()
    {
        $query = Portfolio::query();

        $query->where('customer_id', $this->customer->id);
        $query->where('platform_id', $this->platform->id);

        $query->with(['item']);
        $query->with(['item.image']);
        $query->with(['item.family:name']);
        $query->with(['item.currency']);

        if ($this->customer->is_fulfilment) {
            $query->where('item_type', class_basename(StoredItem::class));
        } else {
            $query->where('item_type', class_basename(Product::class));
        }

        return $query;
    }
}
