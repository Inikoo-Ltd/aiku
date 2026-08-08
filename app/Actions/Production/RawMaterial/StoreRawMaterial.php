<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 21:45:50 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial;

use App\Actions\Production\Production\Hydrators\ProductionHydrateRawMaterials;
use App\Actions\Production\RawMaterial\Hydrators\RawMaterialHydrateFromOrgStock;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRawMaterials;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRawMaterials;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreRawMaterial extends OrgAction
{
    use AsAction;

    public function handle(Production $production, $modelData): RawMaterial
    {
        data_set($modelData, 'group_id', $production->group_id);
        data_set($modelData, 'organisation_id', $production->organisation_id);
        data_set($modelData, 'state', RawMaterialStateEnum::IN_PROCESS, overwrite: false);
        data_set($modelData, 'stock_status', RawMaterialStockStatusEnum::OPTIMAL, overwrite: false);
        data_set($modelData, 'unit_cost', 0, overwrite: false);
        data_set($modelData, 'quantity_on_location', 0, overwrite: false);

        /** @var RawMaterial $rawMaterial */
        $rawMaterial = $production->rawMaterials()->create($modelData);
        $rawMaterial->stats()->create();

        if ($rawMaterial->org_stock_id) {
            RawMaterialHydrateFromOrgStock::run($rawMaterial);
        }

        GroupHydrateRawMaterials::dispatch($production->group);
        OrganisationHydrateRawMaterials::dispatch($production->organisation);
        ProductionHydrateRawMaterials::dispatch($production);
        return $rawMaterial;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        //todo create/find correct permissions
        return $request->user()->authTo("productions_rd.{$this->production->id}.edit");
    }

    public function rules(): array
    {
        return [
            'type'             => ['required', Rule::enum(RawMaterialTypeEnum::class)],
            'state'            => ['sometimes', Rule::enum(RawMaterialStateEnum::class)],
            'code'             => [
                'required',
                'alpha_dash',
                'max:64',
                new IUnique(
                    table: 'raw_materials',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                    ]
                ),
            ],
            'description'      => ['required', 'string', 'max:255'],
            'unit'             => ['required', Rule::enum(RawMaterialUnitEnum::class)],
            'unit_cost'        => ['sometimes', 'numeric', 'min:0'],
            'trade_unit_id'    => [
                'sometimes',
                'nullable',
                Rule::exists('trade_units', 'id')->where('group_id', $this->organisation->group_id),
            ],
            'org_stock_id'     => [
                'sometimes',
                'nullable',
                Rule::exists('org_stocks', 'id')->where('organisation_id', $this->organisation->id),
            ],
            'quantity_on_location' => ['sometimes', 'nullable', 'numeric'],
            'stock_status'     => ['sometimes', Rule::enum(RawMaterialStockStatusEnum::class)],
            'created_at'       => ['sometimes', 'nullable', 'date'],
            'source_id'        => ['sometimes', 'nullable', 'string'],
        ];
    }

    // public function afterValidator($validator)
    // {
    //     dd($validator);
    // }

    public function htmlResponse(RawMaterial $rawMaterial): RedirectResponse
    {
        $production   = $rawMaterial->production;
        $organisation = $rawMaterial->organisation;
        return Redirect::route('grp.org.productions.show.crafts.raw_materials.index', [$organisation, $production]);
    }

    public function action(Production $production, array $modelData): RawMaterial
    {
        $this->asAction = true;
        $this->initialisation($production->organisation, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): RawMaterial
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }
}
