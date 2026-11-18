<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Actions\Production\Artefact\SetArtefactAsMix;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopmentFormulation;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\RawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Gives a blend or intermediate stage its own artefact when it has none yet, e.g. a stage whose trial runs were
 * made on the SKU's artefact before it was a blend. Once the stage is approved the artefact is an in-house
 * ingredient straight away, ready for the finished product's recipe.
 */
class CreateProductDevelopmentFormulationArtefact extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentFormulation $formulation;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->formulation->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canEdit($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentFormulation $formulation): Artefact
    {
        return DB::transaction(function () use ($formulation) {
            ProductDevelopmentFormulation::whereKey($formulation->id)->lockForUpdate()->first();
            $artefact = ProductDevelopmentRecipes::stageArtefact($formulation->refresh());
            $approved = $formulation->samples()->where('status', ProductDevelopmentSampleStatusEnum::APPROVED->value)->orderByDesc('number')->first();

            if ($approved) {
                $this->copyApprovedIngredients($approved, $artefact);
                SetArtefactAsMix::make()->action($artefact, true);
            }

            return $artefact;
        });
    }

    /**
     * The approved trial run was made on another artefact (the SKU's): its recorded ingredients go on the new
     * artefact's first step, so the blend's recipe is the one that was approved, not an empty one.
     */
    private function copyApprovedIngredients(ProductDevelopmentSample $approved, Artefact $artefact): void
    {
        $recipeStep = ArtefactManufactureTask::where('artefact_id', $artefact->id)->orderBy('position')->first();
        if (!$recipeStep || $approved->artefact_id === $artefact->id) {
            return;
        }

        foreach ($approved->recipe['ingredients'] ?? [] as $ingredient) {
            $rawMaterial = RawMaterial::where('organisation_id', $artefact->organisation_id)->where('code', $ingredient['code'])->first();
            if ($rawMaterial && (float) $ingredient['quantity'] > 0 && $rawMaterial->artefact_id !== $artefact->id) {
                AttachRawMaterialToRecipeStep::make()->action($recipeStep, ['raw_material_id' => $rawMaterial->id, 'quantity_per_unit' => (float) $ingredient['quantity']]);
            }
        }
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->formulation->type->hasOwnArtefact()) {
            $validator->errors()->add('formulation', __('The finished product is made on the SKU\'s own artefact'));

            return;
        }

        if ($this->formulation->artefact_id) {
            $validator->errors()->add('formulation', __('This stage already has its artefact'));

            return;
        }

        if (!$this->formulation->productDevelopment->production || !$this->formulation->item->artefact_id) {
            $validator->errors()->add('formulation', __('This SKU has no recipe in Production yet'));
        }
    }

    public function asController(ProductDevelopmentFormulation $productDevelopmentFormulation, ActionRequest $request): Artefact
    {
        $this->formulation = $productDevelopmentFormulation;
        $this->initialisationFromGroup($productDevelopmentFormulation->productDevelopment->group, $request);

        return $this->handle($productDevelopmentFormulation);
    }

    public function action(ProductDevelopmentFormulation $formulation): Artefact
    {
        $this->asAction    = true;
        $this->formulation = $formulation;
        $this->initialisationFromGroup($formulation->productDevelopment->group, []);

        return $this->handle($formulation);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
