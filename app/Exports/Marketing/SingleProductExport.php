<?php

namespace App\Exports\Marketing;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SingleProductExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    use Exportable;

    protected $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Asset|Builder
    {
        $query = Product::query()->where('id', $this->product->id);
        $query->with([
            'family',
            'currency',
            'images',
        ]);
        return $query;
    }

    /** @var Asset $row */
    public function map($row): array
    {
        $webpage = Webpage::where('model_id', $row->id)->where('model_type', 'Product')->first();
        return [
            $row->status->value,
            $row->code,
            '',
            $row->family?->name,
            $row->barcode,
            '', // CPNP number
            '', // TODO: need add column for total price in protfolio
            $row->units,
            $row->unit,
            $row->price, // unit price
            $row->name,
            $row->rrp, // unit RRP check this is correct or not
            '', // TODO: unit net weight
            $row->gross_weight,
            '', // TODO: unit dimensions
            '', // TODO: materials/ingredients
            '', // TODO: webpage description (html)
            $webpage?->description, // webpage description (plain text)
            $row->currency->code, // country of origin
            '', // TODO: tariff code
            '', // TODO: duty rate
            '', // TODO: HTS US
            $row->available_quantity,
            $row->image ? GetImgProxyUrl::run($row->image?->getImage()) : '',
            $row->updated_at,
            '', // TODO: stock updated
            '', // TODO: price updated
            $row->images->sortByDesc('updated_at')->first()?->updated_at,
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
}
