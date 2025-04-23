<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-16h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreMultipleManualPortfolios extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): void
    {
        /** @var Platform $platform */
        $platform = $customer->platforms()->where('type', PlatformTypeEnum::MANUAL)->first();

        DB::transaction(function () use ($customer, $platform, $modelData) {
            foreach (Arr::get($modelData, 'products') as $product) {
                if ($customer->is_fulfilment) {
                    $product = [
                        'stored_item_id' => $product
                    ];
                } else {
                    $product = [
                        'product_id' => $product
                    ];
                }

                StorePortfolio::run($customer, [
                    ...$product,
                    'type'        => PortfolioTypeEnum::MANUAL->value,
                    'platform_id' => $platform->id
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

    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, ActionRequest $request): void
    {
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $this->validatedData);
    }
}
