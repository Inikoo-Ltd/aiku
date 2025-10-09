<?php

namespace App\Exports\Marketing;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsInCollectionExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    use Exportable;
    use DataFeedsMapping;

    protected Collection $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Asset|Builder
    {
        return DB::table('products')
            ->select('products.*', 'product_categories.name as family_name')
            ->leftJoin('product_categories', 'products.family_id', '=', 'product_categories.id')
            ->leftJoin('collection_has_models', 'collection_has_models.model_id', '=', 'products.id')
            ->whereIn('products.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
            ->where('collection_has_models.model_type', 'Product')
            ->where('collection_has_models.collection_id', $this->collection->id)
            ->orderBy('products.id');
    }

}
