<?php

/*
 * Author Louis Perez
 * Created on 02-11-2026-10h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Stops a development for good. Its open tasks are cancelled with it. To change what is being developed
 * (sourced or in-house), the development is cancelled and a new one is started with the same trade units.
 */
class CancelProductDevelopment extends OrgAction
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
        $previousStatus = $productDevelopment->status;

        $productDevelopment->updateQuietly(['status' => ProductDevelopmentStatusEnum::CANCELLED]);

        RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::CANCELLED, ['status' => $previousStatus->value], [
            'status' => ProductDevelopmentStatusEnum::CANCELLED->value,
            'reason' => trim((string) ($modelData['reason'] ?? '')) ?: null,
        ]);

        CreateProductDevelopmentTask::make()->syncProgress($productDevelopment, $user);

        return $productDevelopment;
    }

    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (in_array($this->productDevelopment->status, [ProductDevelopmentStatusEnum::CANCELLED, ProductDevelopmentStatusEnum::COMPLETED], true)) {
            $validator->errors()->add('reason', __('This development is already :status', ['status' => mb_strtolower(ProductDevelopmentStatusEnum::labels()[$this->productDevelopment->status->value])]));
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

    public function htmlResponse(ProductDevelopment $productDevelopment): RedirectResponse
    {
        return Redirect::route('grp.product_developments.show', ['productDevelopment' => $productDevelopment->slug]);
    }
}
