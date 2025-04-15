<?php

/*
 * author Arya Permana - Kirin
 * created on 07-03-2025-11h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaProductManual extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData)
    {
        DB::transaction(function () use ($customer, $modelData) {
            foreach (Arr::get($modelData, 'products') as $product) {
                StorePortfolio::run($customer, [
                    'product_id' => $product,
                    'type' => PortfolioTypeEnum::MANUAL->value,
                ]);
            }
        });
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Customer $customer, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customer, $this->validatedData);
    }
}
