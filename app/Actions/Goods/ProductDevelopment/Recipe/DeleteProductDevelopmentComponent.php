<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-17h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopmentComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Takes a component off an in-house SKU. Purchase orders already placed for it are left as they are.
 */
class DeleteProductDevelopmentComponent extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentComponent $component;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->component->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canManageSamples($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentComponent $component): void
    {
        RecordProductDevelopmentActivity::record($component->productDevelopment, RecordProductDevelopmentActivity::COMPONENT_REMOVED, [], [
            'component' => $component->tradeUnit->code,
            'sku'       => $component->item->tradeUnit->code,
        ]);

        $component->delete();
    }

    public function asController(ProductDevelopmentComponent $productDevelopmentComponent, ActionRequest $request): void
    {
        $this->component = $productDevelopmentComponent;
        $this->initialisationFromGroup($productDevelopmentComponent->productDevelopment->group, $request);

        $this->handle($productDevelopmentComponent);
    }

    public function action(ProductDevelopmentComponent $component): void
    {
        $this->asAction  = true;
        $this->component = $component;
        $this->initialisationFromGroup($component->productDevelopment->group, []);

        $this->handle($component);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
