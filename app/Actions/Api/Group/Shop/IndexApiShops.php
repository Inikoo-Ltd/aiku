<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 19:05:54 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Group\Shop;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Api\Catalogue\ShopApiResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexApiShops extends OrgAction
{
    use WithCatalogueAuthorisation;


    private Group|Organisation $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Organisation && $this->parent->group_id !== group()->id) {
            return false;
        }

        return true;
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }


    public function handle(Organisation|Group $parent): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('shops.name', $value)
                    ->orWhereStartWith('shops.code', $value);
            });
        });


        $queryBuilder = QueryBuilder::for(Shop::class);
        $queryBuilder->with('currency');
        $queryBuilder->where('type', '!=', ShopTypeEnum::FULFILMENT);


        if ($parent instanceof Group) {
            $queryBuilder->where('group_id', $parent->id);
        } else {
            $queryBuilder->where('organisation_id', $parent->id);
        }


        return $queryBuilder
            ->allowedSorts(['code', 'name', 'type', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $shops): \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\Resources\Json\JsonResource
    {
        return ShopApiResource::collection($shops);
    }


}
