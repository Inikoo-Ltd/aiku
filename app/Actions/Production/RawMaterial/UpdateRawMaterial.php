<?php

namespace App\Actions\Production\RawMaterial;

use App\Actions\OrgAction;
use App\Actions\Production\Production\Hydrators\ProductionHydrateRawMaterials;
use App\Actions\Production\RawMaterial\Hydrators\RawMaterialHydrateUniversalSearch;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRawMaterials;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRawMaterials;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRawMaterial extends OrgAction
{
    use WithActionUpdate;

    private RawMaterial $rawMaterial;

    public function handle(RawMaterial $rawMaterial, array $modelData): RawMaterial
    {
        $rawMaterial = $this->update($rawMaterial, $modelData);
        if ($rawMaterial->wasChanged('state')) {
            GroupHydrateRawMaterials::dispatch($rawMaterial->group);
            OrganisationHydrateRawMaterials::dispatch($rawMaterial->organisation);
            ProductionHydrateRawMaterials::dispatch($rawMaterial->production);
        }
        RawMaterialHydrateUniversalSearch::dispatch($rawMaterial);

        return $rawMaterial;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("productions_rd.{$this->production->id}.edit");
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(RawMaterialTypeEnum::class)],
            'state' => ['sometimes', Rule::enum(RawMaterialStateEnum::class)],
            'code' => [
                'sometimes',
                'alpha_dash',
                'max:64',
                new IUnique(
                    table: 'raw_materials',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value' => $this->organisation->id,
                        ],
                        [
                            'column' => 'id',
                            'value' => $this->rawMaterial->id,
                            'operation' => '!=',
                        ],

                    ]
                ),
            ],
            'description' => ['sometimes', 'string', 'max:255'],
            'unit' => ['sometimes', Rule::enum(RawMaterialUnitEnum::class)],
            'unit_cost' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function asController(Organisation $organisation, Production $production, RawMaterial $rawMaterial, ActionRequest $request): RawMaterial
    {
        $this->rawMaterial = $rawMaterial;
        $this->initialisationFromProduction($rawMaterial->production, $request);

        return $this->handle(
            rawMaterial: $rawMaterial,
            modelData: $this->validatedData
        );
    }

    public function action(RawMaterial $rawMaterial, $modelData): RawMaterial
    {
        $this->asAction = true;
        $this->rawMaterial = $rawMaterial;
        $this->initialisation($rawMaterial->organisation, $modelData);

        return $this->handle(
            rawMaterial: $rawMaterial,
            modelData: $this->validatedData
        );
    }
}
