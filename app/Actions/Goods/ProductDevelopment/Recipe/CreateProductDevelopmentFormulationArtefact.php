<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\SetArtefactAsMix;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Goods\ProductDevelopmentFormulation;
use App\Models\Production\Artefact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Gives a blend or intermediate stage its own artefact when it has none yet or its artefact was cancelled, e.g. a
 * stage whose trial runs were made on the SKU's artefact before it was a blend. Once the stage is approved and no
 * share waits for its units the artefact is an in-house ingredient straight away, ready for the finished product's
 * recipe.
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
            $approved = $formulation->samples()->where('status', ProductDevelopmentSampleStatusEnum::APPROVED->value)->reorder()->orderByDesc('number')->first();

            if ($approved && !FormulationPercentages::hasPendingUnits($approved) && count(ProductDevelopmentRecipes::recipe($artefact)['ingredients']) > 0) {
                SetArtefactAsMix::make()->action($artefact, true);
            }

            return $artefact;
        });
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->formulation->type->hasOwnArtefact()) {
            $validator->errors()->add('formulation', __('The finished product is made on the SKU\'s own artefact'));

            return;
        }

        if ($this->formulation->artefact_id && $this->formulation->artefact?->state !== ArtefactStateEnum::CANCELLED) {
            $validator->errors()->add('formulation', __('This stage already has its artefact'));

            return;
        }

        if ($this->formulation->samples()->whereIn('status', [ProductDevelopmentSampleStatusEnum::IN_PROGRESS->value, ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW->value])->exists()) {
            $validator->errors()->add('formulation', __('A trial run of this stage is still being made: its artefact is created once it is reviewed'));

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
