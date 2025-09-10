<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:03:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexMasterProductImages extends OrgAction
{
    public function handle(MasterAsset $masterAsset, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Media::class);
        $queryBuilder->leftJoin('model_has_media', 'media.id', 'model_has_media.media_id');
        $queryBuilder->where('model_has_media.model_id', $masterAsset->id);
        $queryBuilder->where('model_has_media.model_type', 'MasterAsset');


        $queryBuilder
            ->defaultSort('media.id')
            ->select([
                'media.*',
                'model_has_media.sub_scope as sub_scope',
            ]);

        return $queryBuilder->allowedSorts(['size', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterAsset $masterAsset, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($masterAsset, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }



            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [

                        'count' => $masterAsset->stats->number_images,

                    ]
                );


            $table
                ->column(key: 'grabbable_area', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'image', label: __('Image'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'size', label: __('Size'), canBeHidden: false, sortable: true, searchable: true);


        };
    }

}
