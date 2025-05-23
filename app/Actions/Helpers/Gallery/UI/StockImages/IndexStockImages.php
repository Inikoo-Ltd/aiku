<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Oct 2023 08:09:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Gallery\UI\StockImages;

use App\Actions\GrpAction;
use App\Enums\Web\Banner\BannerTypeEnum;
use App\Http\Resources\Helpers\ImageResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Group;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexStockImages extends GrpAction
{
    public function handle(Group $group, $subScope, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('media.name', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Media::class);
        $queryBuilder->leftJoin('model_has_media', 'model_has_media.media_id', '=', 'media.id');
        $queryBuilder->where('model_has_media.scope', 'banners-stock');

        $queryBuilder->where('model_has_media.sub_scope', 'like', $subScope->value.'-%');


        $queryBuilder->where('model_has_media.group_id', $group->id);

        if (isset(request()->query()['json']) && request()->query()['json'] === 'true' || (function_exists('request') && request() && request()->expectsJson())) {
            $queryBuilder->orderBy('media.created_at', 'desc');
        }

        return $queryBuilder
            ->defaultSort('media.name')
            ->select(['media.name', 'media.id', 'size', 'mime_type', 'file_name', 'disk', 'media.slug', 'is_animated'])
            ->allowedSorts(['name', 'size'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $exportLinks) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withExportLinks($exportLinks)
                ->column(key: 'name', label: __('name'), sortable: true)
                ->column(key: 'thumbnail', label: __('image'))
                ->column(key: 'size', label: __('size'), sortable: true)
                ->column(key: 'select', label: __('Operations'))
                ->defaultSort('name');
        };
    }

    // todo delete this method
    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($this->group, BannerTypeEnum::LANDSCAPE);
    }

    public function landscape(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($this->group, BannerTypeEnum::LANDSCAPE);
    }

    public function square(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($this->group, BannerTypeEnum::SQUARE);
    }


    public function jsonResponse($medias): AnonymousResourceCollection
    {
        return ImageResource::collection($medias);
    }


}
