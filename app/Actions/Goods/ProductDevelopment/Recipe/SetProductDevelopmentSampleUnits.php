<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\Production\ArtefactManufactureTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * HELP-4852: the units per kg of blend of the shares that do not convert on their own (a sample measured in ml, a
 * raw material with no weight), entered by whoever turns the formulation into the production recipe. The shares
 * never change here: a different share is a new formulation. Once every share has its units, an approved blend
 * becomes the in-house ingredient the finished product's recipe uses.
 */
class SetProductDevelopmentSampleUnits extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentSample $sample;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->sample->productDevelopment;
        if ($this->canViewProductDevelopment($request->user(), $productDevelopment) && $this->productDevelopmentAccess()->canEdit($request->user(), $productDevelopment)) {
            return true;
        }

        return $this->sample->jobOrder && RecordTrialRunFormulation::canRecord($request->user(), $this->sample->jobOrder);
    }

    public function handle(ProductDevelopmentSample $sample, array $modelData): ProductDevelopmentSample
    {
        DB::transaction(function () use ($sample, $modelData) {
            $formulation = $sample->recipe['formulation'];
            $entered     = collect($modelData['lines'])->mapWithKeys(fn (array $line) => [(int) $line['raw_material_id'] => round((float) $line['units_per_kg'], 8)]);
            $artefact    = FormulationPercentages::recipeArtefact($sample);
            $recipeStep  = $artefact->id === $sample->artefact_id
                ? ArtefactManufactureTask::where('artefact_id', $artefact->id)->findOrFail($formulation['recipe_step_id'])
                : ArtefactManufactureTask::where('artefact_id', $artefact->id)->orderBy('position')->firstOrFail();

            foreach ($entered as $rawMaterialId => $unitsPerKg) {
                AttachRawMaterialToRecipeStep::make()->action($recipeStep, ['raw_material_id' => $rawMaterialId, 'quantity_per_unit' => $unitsPerKg]);
            }

            $formulation['lines'] = collect($formulation['lines'])->map(fn (array $line) => $entered->has($line['raw_material_id'])
                ? [...$line, 'units_per_kg' => $entered->get($line['raw_material_id']), 'manual' => true]
                : $line)->all();
            $recipe = [...ProductDevelopmentRecipes::recipe($artefact->fresh()), 'formulation' => $formulation];

            $sample->update(['recipe' => $recipe]);
            $jobOrder = $sample->jobOrder;
            if ($jobOrder && isset($jobOrder->data['formulation'])) {
                $data = $jobOrder->data;
                Arr::set($data, 'formulation.recipe', $recipe);
                $jobOrder->update(['data' => $data]);
            }

            if ($sample->status === ProductDevelopmentSampleStatusEnum::APPROVED) {
                ReviewProductDevelopmentSample::offerAsIngredient($sample->refresh());
            }
        });

        return $sample;
    }

    public function rules(): array
    {
        return [
            'lines'                   => ['required', 'array', 'min:1'],
            'lines.*.raw_material_id' => ['required', 'integer', 'distinct'],
            'lines.*.units_per_kg'    => ['required', 'numeric', 'gte:0.00000001', 'max:1000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'lines.*.units_per_kg.required' => __('Enter the units in 1 kg of the blend'),
            'lines.*.units_per_kg.gte'      => __('Enter the units in 1 kg of the blend'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $lines = collect($this->sample->recipe['formulation']['lines'] ?? [])->keyBy('raw_material_id');
        if ($lines->isEmpty() || !FormulationPercentages::isFor($this->sample->formulation) || !FormulationPercentages::recipeArtefact($this->sample)) {
            $validator->errors()->add('lines', __('This trial run has no formulation in percentages'));

            return;
        }

        $latest = ProductDevelopmentSample::where('product_development_formulation_id', $this->sample->product_development_formulation_id)->max('number');
        if ((int) $latest !== (int) $this->sample->number) {
            $validator->errors()->add('lines', __('A later trial run of this stage holds the recipe now: enter the units there'));

            return;
        }

        foreach ((array) $this->get('lines', []) as $index => $line) {
            $formulationLine = $lines->get((int) Arr::get($line, 'raw_material_id'));
            if (!$formulationLine) {
                $validator->errors()->add("lines.$index.raw_material_id", __('This raw material is not in the formulation'));
            } elseif ($formulationLine['units_per_kg'] !== null && !($formulationLine['manual'] ?? false)) {
                $validator->errors()->add("lines.$index.raw_material_id", __('Its units come from its share and weight'));
            }
        }
    }

    public function asController(ProductDevelopmentSample $productDevelopmentSample, ActionRequest $request): ProductDevelopmentSample
    {
        $this->sample = $productDevelopmentSample;
        $this->initialisationFromGroup($productDevelopmentSample->productDevelopment->group, $request);

        return $this->handle($productDevelopmentSample, $this->validatedData);
    }

    public function action(ProductDevelopmentSample $sample, array $modelData): ProductDevelopmentSample
    {
        $this->asAction = true;
        $this->sample   = $sample;
        $this->initialisationFromGroup($sample->productDevelopment->group, $modelData);

        return $this->handle($sample, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
