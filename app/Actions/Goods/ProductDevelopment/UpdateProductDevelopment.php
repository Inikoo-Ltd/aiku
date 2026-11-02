<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-02h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Models\Goods\ProductDevelopment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductDevelopment extends OrgAction
{
    use WithActionUpdate;
    use WithProductDevelopmentAuthorisation;

    private ?ProductDevelopment $productDevelopment = null;

    private function target(): ProductDevelopment
    {
        return $this->productDevelopment ?? request()->route('productDevelopment');
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->target())
            && $this->canEditProductDevelopment($request->user(), $this->target());
    }

    public function handle(ProductDevelopment $productDevelopment, array $modelData): ProductDevelopment
    {
        return $this->update($productDevelopment, $modelData);
    }

    public function rules(): array
    {
        return [
            'name'                => ['sometimes', 'required', 'string', 'max:255'],
            'brief'               => ['sometimes', 'nullable', 'string', 'max:20000'],
            'target_landing_date' => ['sometimes', 'nullable', 'date'],
            'target_online_date'  => ['sometimes', 'nullable', 'date'],
            'markets'             => ['sometimes', 'array'],
            'markets.*'           => [Rule::enum(TradeUnitMarketEnum::class)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $landing = $this->has('target_landing_date') ? $this->get('target_landing_date') : $this->target()->target_landing_date?->toDateString();
        $online  = $this->has('target_online_date') ? $this->get('target_online_date') : $this->target()->target_online_date?->toDateString();

        if ($landing && $online && Carbon::parse($online)->lt(Carbon::parse($landing))) {
            $validator->errors()->add('target_online_date', __('The online date cannot be before the landing date'));
        }
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): ProductDevelopment
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, array $modelData): ProductDevelopment
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        return $this->handle($productDevelopment, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
