<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-18h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Purchasing confirms on the buyer's list that a component of an in-house development is bought (or takes it
 * back). Aiku does not track these purchases, so this records who confirmed it, when, and their note, for
 * every SKU using the component.
 */
class ConfirmProductDevelopmentComponentPurchase extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopment $productDevelopment;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->productDevelopment)
            && $this->productDevelopmentAccess()->canApproveComponents($request->user(), $this->productDevelopment);
    }

    public function handle(ProductDevelopment $productDevelopment, User $user, array $modelData): void
    {
        $confirmed = (bool) $modelData['confirmed'];

        $productDevelopment->components()->where('trade_unit_id', $modelData['trade_unit_id'])->update([
            'purchase_confirmed_at'    => $confirmed ? now() : null,
            'purchase_confirmed_by_id' => $confirmed ? $user->id : null,
            'purchase_note'            => $confirmed ? (trim((string) ($modelData['note'] ?? '')) ?: null) : null,
        ]);

        RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::COMPONENT_PURCHASE_CONFIRMED, [], [
            'component' => TradeUnit::find($modelData['trade_unit_id'])?->code,
            'confirmed' => $confirmed,
            'note'      => $modelData['note'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'trade_unit_id' => ['required', 'integer', Rule::exists('product_development_components', 'trade_unit_id')->where('product_development_id', $this->productDevelopment->id)],
            'confirmed'     => ['required', 'boolean'],
            'note'          => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->get('confirmed') || !$this->get('trade_unit_id')) {
            return;
        }

        $pending = $this->productDevelopment->components()
            ->where('trade_unit_id', $this->get('trade_unit_id'))
            ->where('status', '!=', ProductDevelopmentComponentStatusEnum::APPROVED->value)
            ->exists();
        if ($pending) {
            $validator->errors()->add('trade_unit_id', __('Approve the component for purchasing first'));
        }
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): void
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        $this->handle($productDevelopment, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, User $user, array $modelData): void
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        $this->handle($productDevelopment, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
