<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Production\ManufactureTasksResource;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetManufactureTasks extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
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
                $query->whereWith('manufacture_tasks.code', $value)
                    ->orWhereWith('manufacture_tasks.name', $value);
            });
        });

        return QueryBuilder::for(ManufactureTask::class)
            ->where('manufacture_tasks.production_id', $production->id)
            ->defaultSort('manufacture_tasks.code')
            ->select([
                'manufacture_tasks.id',
                'manufacture_tasks.slug',
                'manufacture_tasks.code',
                'manufacture_tasks.name',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $manufactureTasks): AnonymousResourceCollection
    {
        return ManufactureTasksResource::collection($manufactureTasks);
    }
}
