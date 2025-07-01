<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-18h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Helpers\Brand\Json;

use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\BrandResource;
use App\Models\Helpers\Brand;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetIrisBrands extends IrisAction
{
    public function handle()
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('brands.name', $value);
            });
        });
        $perPage = 250;
        $queryBuilder = QueryBuilder::for(Brand::class);

        $queryBuilder
            ->defaultSort('brands.id')
            ->select([
                'brands.id',
                'brands.reference',
                'brands.name',
                'brands.slug',
            ]);

        return $queryBuilder->defaultSort('name')
            ->allowedSorts(['name', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withIrisPaginator($perPage)
            ->withQueryString();
    }

    public function jsonResponse($brands): AnonymousResourceCollection
    {
        return BrandResource::collection($brands);
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle();
    }

}
