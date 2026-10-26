<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-17h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Models\Goods\ProductDevelopmentComponent;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Approves a component for purchasing, or takes the approval back. Each component is approved on its own,
 * so an approved bottle can be ordered while the rest of the SKU is still being developed.
 */
class ApproveProductDevelopmentComponent extends OrgAction
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
            && $this->productDevelopmentAccess()->canApproveComponents($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentComponent $component, User $user, array $modelData): ProductDevelopmentComponent
    {
        $approved = (bool) $modelData['approved'];

        $component->update([
            'status'         => $approved ? ProductDevelopmentComponentStatusEnum::APPROVED : ProductDevelopmentComponentStatusEnum::PENDING,
            'approved_by_id' => $approved ? $user->id : null,
            'approved_at'    => $approved ? now() : null,
        ]);

        RecordProductDevelopmentActivity::record($component->productDevelopment, RecordProductDevelopmentActivity::COMPONENT_APPROVED, [], [
            'component' => $component->tradeUnit->code,
            'approved'  => $approved,
        ]);

        return $component;
    }

    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
        ];
    }

    public function asController(ProductDevelopmentComponent $productDevelopmentComponent, ActionRequest $request): ProductDevelopmentComponent
    {
        $this->component = $productDevelopmentComponent;
        $this->initialisationFromGroup($productDevelopmentComponent->productDevelopment->group, $request);

        return $this->handle($productDevelopmentComponent, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentComponent $component, User $user, array $modelData): ProductDevelopmentComponent
    {
        $this->asAction  = true;
        $this->component = $component;
        $this->initialisationFromGroup($component->productDevelopment->group, $modelData);

        return $this->handle($component, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
