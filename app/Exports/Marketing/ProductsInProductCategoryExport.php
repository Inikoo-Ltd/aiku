<?php

namespace App\Exports\Marketing;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
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
    use DataFeedsMapping;

    protected ProductCategory $productCategory;

    public function __construct(ProductCategory $productCategory)
    {
        $this->productCategory = $productCategory;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Asset|Builder
    {
        if ($this->productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $query = Product::query()->where('department_id', $this->productCategory->id)->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);
        } elseif ($this->productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $query = Product::query()->where('family_id', $this->productCategory->id)->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);
        } else {
            $query = Product::query()->where('sub_department_id', $this->productCategory->id)->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);
        }

        $query->with([
            'family',
            'currency',
            'images',
        ]);


        return $query;
    }

}
