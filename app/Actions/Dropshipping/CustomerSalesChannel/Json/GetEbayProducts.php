<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-10h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\Dropshipping\WooCommerce\Product\GetProductForWooCommerce;
use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetEbayProducts extends OrgAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array|null
    {
        $query = Arr::get($modelData, 'query', '');

        if ($query === null) {
            $query = '';
        }

        return GetProductForWooCommerce::run($customerSalesChannel->user, $query);
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge([
            'query' => $request->get('query')
        ]);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request)
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
