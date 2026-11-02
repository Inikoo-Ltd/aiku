<?php

/*
 * Author Louis Perez
 * Created on 02-11-2026-13h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Production\Production\ProductionStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Switches a development between a sourced product and in-house production while it is still early enough
 * that nothing was made or bought for the other kind.
 */
class ChangeProductDevelopmentKind extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopment $productDevelopment;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->productDevelopment)
            && $this->canEditProductDevelopment($request->user(), $this->productDevelopment);
    }

    public function handle(ProductDevelopment $productDevelopment, User $user, array $modelData): ProductDevelopment
    {
        if ($modelData['kind'] === ProductDevelopmentKindEnum::RECIPE->value) {
            return ConvertProductDevelopmentToInHouse::make()->action($productDevelopment, $user, Production::findOrFail($modelData['production_id']));
        }

        return ConvertProductDevelopmentToSourced::make()->action($productDevelopment, $user);
    }

    public static function blockedReason(ProductDevelopment $productDevelopment): ?string
    {
        return $productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE
            ? ConvertProductDevelopmentToSourced::blockedReason($productDevelopment)
            : ConvertProductDevelopmentToInHouse::blockedReason($productDevelopment);
    }

    public function rules(): array
    {
        return [
            'kind'          => ['required', Rule::enum(ProductDevelopmentKindEnum::class)],
            'production_id' => [
                'required_if:kind,'.ProductDevelopmentKindEnum::RECIPE->value,
                'nullable',
                'integer',
                Rule::exists('productions', 'id')->where('group_id', $this->group->id)->whereNot('state', ProductionStateEnum::CLOSED->value),
            ],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'production_id.required_if' => __('Choose the production unit that makes it'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->get('kind') === $this->productDevelopment->kind->value) {
            $validator->errors()->add('kind', __('It is already :kind', ['kind' => mb_strtolower(ProductDevelopmentKindEnum::labels()[$this->productDevelopment->kind->value])]));

            return;
        }

        if ($reason = self::blockedReason($this->productDevelopment)) {
            $validator->errors()->add('kind', $reason);
        }
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): ProductDevelopment
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, User $user, array $modelData): ProductDevelopment
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        return $this->handle($productDevelopment, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
