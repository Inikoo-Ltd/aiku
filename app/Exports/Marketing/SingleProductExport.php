<?php

namespace App\Exports\Marketing;

use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SingleProductExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    use Exportable;
    use DataFeedsMapping;

    protected Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|\Laravel\Scout\Builder|Builder
    {

        return DB::table('products')
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
            ->where('products.id', $this->product->id)
            ->orderBy('id');
    }
}
