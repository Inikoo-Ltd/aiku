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
use App\Models\Goods\ProductDevelopmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Removes a formulation stage added by mistake: only one with no trial runs, and never the SKU's only stage.
 */
class DeleteProductDevelopmentFormulation extends OrgAction
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

    public function handle(ProductDevelopmentFormulation $formulation): void
    {
        DB::transaction(function () use ($formulation) {
            ProductDevelopmentItem::whereKey($formulation->product_development_item_id)->lockForUpdate()->first();
            $formulation->delete();
            ProductDevelopmentFormulation::where('product_development_item_id', $formulation->product_development_item_id)
                ->where('position', '>', $formulation->position)
                ->orderBy('position')
                ->get()
                ->each(fn (ProductDevelopmentFormulation $later) => $later->update(['position' => $later->position - 1]));
        });
    }

    public function afterValidator(Validator $validator): void
    {
        if (!ProductDevelopmentRecipes::canChangeFormulations($this->formulation->productDevelopment)) {
            $validator->errors()->add('formulation', __('Stages can only be removed while the development is formulating'));

            return;
        }

        if ($this->formulation->samples()->exists()) {
            $validator->errors()->add('formulation', __('This stage has trial runs and cannot be removed'));

            return;
        }

        if (ProductDevelopmentFormulation::where('product_development_item_id', $this->formulation->product_development_item_id)->count() <= 1) {
            $validator->errors()->add('formulation', __('A SKU needs at least one formulation stage'));
        }
    }

    public function asController(ProductDevelopmentFormulation $productDevelopmentFormulation, ActionRequest $request): void
    {
        $this->formulation = $productDevelopmentFormulation;
        $this->initialisationFromGroup($productDevelopmentFormulation->productDevelopment->group, $request);

        $this->handle($productDevelopmentFormulation);
    }

    public function action(ProductDevelopmentFormulation $formulation): void
    {
        $this->asAction    = true;
        $this->formulation = $formulation;
        $this->initialisationFromGroup($formulation->productDevelopment->group, []);

        $this->handle($formulation);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
