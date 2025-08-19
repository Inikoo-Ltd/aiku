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
            ->select('products.*', 'product_categories.name as family_name')
            ->leftJoin('product_categories', 'products.family_id', '=', 'product_categories.id')
            ->where('products.slug', $this->product->slug)
            ->orderBy('id');
    }
}
