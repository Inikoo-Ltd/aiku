<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Production\RawMaterialsResource;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetRawMaterials extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.view"]);
    }

    public function asController(Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function handle(Production $production): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('raw_materials.code', $value)
                    ->orWhereWith('raw_materials.description', $value);
            });
        });

        return QueryBuilder::for(RawMaterial::class)
            ->where('raw_materials.production_id', $production->id)
            ->defaultSort('raw_materials.code')
            ->select([
                'raw_materials.id',
                'raw_materials.slug',
                'raw_materials.code',
                'raw_materials.description',
                'raw_materials.unit',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $rawMaterials): AnonymousResourceCollection
    {
        return RawMaterialsResource::collection($rawMaterials);
    }
}
