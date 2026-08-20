<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-10h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\Dropshipping\Shopify\Product\GetShopifyListedProducts;
use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetShopifyProducts extends OrgAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array|null
    {
        return [
            'products' => GetShopifyListedProducts::run(
                $customerSalesChannel->user,
                (string) Arr::get($modelData, 'query', ''),
                (int) Arr::get($modelData, 'offset', 0),
                (int) Arr::get($modelData, 'limit', 50)
            )
        ];
    }

    public function rules(): array
    {
        return [
            'query'  => ['nullable', 'string'],
            'offset' => ['nullable', 'numeric'],
            'limit'  => ['nullable', 'numeric', 'min:1', 'max:100']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge([
            'query'  => $request->input('query'),
            'offset' => $request->input('offset'),
            'limit'  => $request->input('limit'),
        ]);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request)
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
