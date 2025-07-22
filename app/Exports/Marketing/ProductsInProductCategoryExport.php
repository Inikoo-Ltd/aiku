<?php

namespace App\Exports\Marketing;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsInProductCategoryExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    use Exportable;

    protected $productCategory;
    protected $type;

    public function __construct(ProductCategory $productCategory, string $type)
    {
        $this->type = $type;
        $this->productCategory = $productCategory;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Asset|Builder
    {
        if($this->type == 'department') {
            return Product::query()->where('department_id', $this->productCategory->id)->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);
        } elseif ($this->type == 'family') {
            return Product::query()->where('family_id', $this->productCategory->id)->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);
        } else {
            return Product::query()->where('sub_department_id', $this->productCategory->id)->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);
        }
    }

    /** @var Asset $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->code,
            $row->state,
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
