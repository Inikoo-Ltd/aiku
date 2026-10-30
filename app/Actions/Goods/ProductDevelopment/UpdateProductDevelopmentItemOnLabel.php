<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-19h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentLabelContent;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Models\Goods\ProductDevelopmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Puts a piece of a SKU's pack text on its label or leaves it off. Only what is on the label reaches the
 * Label & packaging view and the designers.
 */
class UpdateProductDevelopmentItemOnLabel extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentItem $item;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->item->productDevelopment;
        $access             = $this->productDevelopmentAccess();

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && ($access->canEdit($request->user(), $productDevelopment) || in_array(ProductDevelopmentMemberRoleEnum::COMPLIANCE, $access->memberRoles($request->user(), $productDevelopment), true));
    }

    public function handle(ProductDevelopmentItem $item, array $modelData): ProductDevelopmentItem
    {
        $excluded = collect($item->label_excluded ?? [])
            ->reject(fn (string $field) => $field === $modelData['field'])
            ->when(!$modelData['include'], fn ($fields) => $fields->push($modelData['field']))
            ->sort()
            ->values()
            ->all();

        $item->update(['label_excluded' => $excluded]);

        return $item;
    }

    public function rules(): array
    {
        return [
            'field'   => ['required', Rule::in(GetProductDevelopmentLabelContent::LABEL_FIELDS)],
            'include' => ['required', 'boolean'],
        ];
    }

    public function asController(ProductDevelopmentItem $productDevelopmentItem, ActionRequest $request): ProductDevelopmentItem
    {
        $this->item = $productDevelopmentItem;
        $this->initialisationFromGroup($productDevelopmentItem->productDevelopment->group, $request);

        return $this->handle($productDevelopmentItem, $this->validatedData);
    }

    public function action(ProductDevelopmentItem $item, array $modelData): ProductDevelopmentItem
    {
        $this->asAction = true;
        $this->item     = $item;
        $this->initialisationFromGroup($item->productDevelopment->group, $modelData);

        return $this->handle($item, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
