<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Oct 2023 08:09:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Http\Resources\Helpers\ImageResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class GetProductUploadedImages extends OrgAction
{
    public function handle(Product $product): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('media.name', $value);
            });
        });
        // if ($prefix) {
        //     InertiaTable::updateQueryBuilderParameters($prefix);
        // }
        $mediaIds = $product->images()->pluck('media.id');

        $queryBuilder = QueryBuilder::for(Media::class)
        ->leftJoin('model_has_media', 'model_has_media.media_id', '=', 'media.id')
        ->whereNotIn('model_has_media.media_id', $mediaIds);

        return $queryBuilder
            ->defaultSort('media.name')
            ->where('media.group_id', $product->group_id)
            ->where('collection_name', 'image')
            ->select(['media.name', 'media.id', 'size', 'mime_type', 'file_name', 'disk', 'media.slug', 'is_animated'])
            ->allowedSorts(['name', 'size'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    // public function tableStructure(?array $modelOperations = null, $prefix = null, ?array $exportLinks = null): Closure
    // {
    //     return function (InertiaTable $table) use ($modelOperations, $prefix, $exportLinks) {
    //         if ($prefix) {
    //             $table
    //                 ->name($prefix)
    //                 ->pageName($prefix . 'Page');
    //         }


    //         $table
    //             ->withModelOperations($modelOperations)
    //             ->withGlobalSearch()
    //             ->withExportLinks($exportLinks)
    //             ->column(key: 'name', label: __('Name'), sortable: true)
    //             ->column(key: 'thumbnail', label: __('image'))
    //             ->column(key: 'size', label: __('size'), sortable: true)
    //             ->column(key: 'select', label: __('Operations'))
    //             ->defaultSort('name');
    //     };
    // }

    public function asController(Organisation $organisation, Shop $shop, Product $product, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($product);
    }


    public function jsonResponse($medias): AnonymousResourceCollection
    {
        return ImageResource::collection($medias);
    }


}
