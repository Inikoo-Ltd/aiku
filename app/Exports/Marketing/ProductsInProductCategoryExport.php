<?php

namespace App\Exports\Marketing;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
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

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|\Laravel\Scout\Builder|Builder
    {
        $query = DB::table('products')
            ->select(
                'products.*',
                'families.name as family_name',
                'families.code as family_code',
                'departments.name as department_name',
                'departments.code as department_code',
                'sub_departments.name as subdepartment_name',
                'sub_departments.code as subdepartment_code'
            )
            ->leftJoin('product_categories as families', 'products.family_id', '=', 'families.id')
            ->leftJoin('product_categories as departments', 'products.department_id', '=', 'departments.id')
            ->leftJoin('product_categories as sub_departments', 'products.sub_department_id', '=', 'sub_departments.id')
            ->whereIn('products.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);


        if ($this->productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $query->where('products.department_id', $this->productCategory->id);
        } elseif ($this->productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $query->where('products.family_id', $this->productCategory->id);
        } else {
            $query->where('products.sub_department_id', $this->productCategory->id);
        }

        return $query->orderBy('products.id');
    }

}
