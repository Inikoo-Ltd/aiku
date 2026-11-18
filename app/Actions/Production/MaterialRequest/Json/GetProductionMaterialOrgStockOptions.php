<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\MaterialRequest\Json;

use App\Actions\OrgAction;
use App\Actions\Production\MaterialRequest\MaterialsCheckAuthorisation;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Production;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetProductionMaterialOrgStockOptions extends OrgAction
{
    /**
     * SKOs the factory can ask the warehouse for, with what the shelves and the factory hold.
     *
     * @return Collection<int, array{id: int, code: string, name: string|null, warehouse_skos: float, factory_skos: float}>
     */
    public function handle(Production $production, string $search): Collection
    {
        $factoryLocationId = (int) $production->factory_location_id;

        return OrgStock::query()
            ->where('org_stocks.organisation_id', $production->organisation_id)
            ->whereIn('org_stocks.state', [OrgStockStateEnum::ACTIVE, OrgStockStateEnum::DISCONTINUING])
            ->where(fn ($query) => $query->whereStartWith('org_stocks.code', $search)->orWhereAnyWordStartWith('org_stocks.name', $search))
            ->orderByRaw('lower(org_stocks.code) = lower(?) desc', [$search])
            ->orderBy('org_stocks.code')
            ->limit(20)
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                DB::raw("coalesce((select sum(quantity) from location_org_stocks where org_stock_id = org_stocks.id and location_id <> $factoryLocationId), 0) as warehouse_skos"),
                DB::raw("coalesce((select sum(quantity) from location_org_stocks where org_stock_id = org_stocks.id and location_id = $factoryLocationId), 0) as factory_skos"),
            ])
            ->get()
            ->map(fn (OrgStock $row) => [
                'id'             => (int) $row->id,
                'code'           => $row->code,
                'name'           => $row->name,
                'warehouse_skos' => (float) $row->warehouse_skos,
                'factory_skos'   => (float) $row->factory_skos,
            ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return MaterialsCheckAuthorisation::canRequestMaterials($request->user(), $this->production);
    }

    public function rules(): array
    {
        return [
            'search' => ['required', 'string', 'max:64'],
        ];
    }

    public function asController(Production $production, ActionRequest $request): Collection
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData['search']);
    }
}
