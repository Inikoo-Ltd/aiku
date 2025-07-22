<?php

namespace App\Exports\Marketing;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsInShopExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    use Exportable;

    protected $shop;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Asset|Builder
    {
        return Product::query()->where('shop_id', $this->shop->id)->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);
    }

    /** @var Asset $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->code,
            $row->state->value,
            $row->name,
            $row->available_quantity,
            $row->gross_weight,
            $row->price,
            $row->rrp,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Code',
            'State',
            'Name',
            'Quantity',
            'Weight',
            'Price',
            'RRP',
        ];
    }
}
