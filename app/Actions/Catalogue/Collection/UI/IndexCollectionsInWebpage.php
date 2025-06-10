<?php
/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-08h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
use App\Actions\Catalogue\ProductCategory\UI\ShowSubDepartment;
use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\CollectionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCollectionsInWebpage extends OrgAction
{
    public function handle(Webpage $webpage, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->leftjoin('collection_stats', 'collections.id', 'collection_stats.collection_id');
        $queryBuilder->leftjoin('webpage_has_collections', 'collections.id', 'webpage_has_collections.collection_id');
        $queryBuilder->where('webpage_has_collections.webpage_id', $webpage->id);
        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
                'collection_stats.number_departments',
                'collection_stats.number_families',
                'collection_stats.number_products',
                'collection_stats.number_collections'
            ]);

        return $queryBuilder
            ->allowedSorts(['code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }
}
