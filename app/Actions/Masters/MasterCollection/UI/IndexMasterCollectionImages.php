<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:03:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\Masters\MasterProductCategory\UI\IndexMasterProductCategoryImages;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexMasterCollectionImages extends OrgAction
{
    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Media::class);
        $queryBuilder->leftJoin('model_has_media', 'media.id', 'model_has_media.media_id');
        $queryBuilder->where('model_has_media.model_id', $masterCollection->id);
        $queryBuilder->where('model_has_media.model_type', 'MasterCollection');


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
        return IndexMasterProductCategoryImages::make()->tableStructure($masterAsset, $prefix);
    }

}
