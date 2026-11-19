<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopmentFormulation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Renames a formulation stage or changes the artisan proposed for its trial runs. A trial run already waiting
 * for release keeps the artisan proposed when it was raised.
 */
class UpdateProductDevelopmentFormulation extends OrgAction
{
    use WithProductDevelopmentAuthorisation;
    use WithProductDevelopmentFormulationRules;

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

    public function handle(ProductDevelopmentFormulation $formulation, array $modelData): ProductDevelopmentFormulation
    {
        if (Arr::has($modelData, 'name')) {
            $modelData['name'] = trim($modelData['name']);
        }
        $formulation->update(Arr::only($modelData, ['name', 'artisan_id']));

        return $formulation;
    }

    public function rules(): array
    {
        return $this->formulationRules($this->formulation->productDevelopment, false);
    }

    public function asController(ProductDevelopmentFormulation $productDevelopmentFormulation, ActionRequest $request): ProductDevelopmentFormulation
    {
        $this->formulation = $productDevelopmentFormulation;
        $this->initialisationFromGroup($productDevelopmentFormulation->productDevelopment->group, $request);

        return $this->handle($productDevelopmentFormulation, $this->validatedData);
    }

    public function action(ProductDevelopmentFormulation $formulation, array $modelData): ProductDevelopmentFormulation
    {
        $this->asAction    = true;
        $this->formulation = $formulation;
        $this->initialisationFromGroup($formulation->productDevelopment->group, $modelData);

        return $this->handle($formulation, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
