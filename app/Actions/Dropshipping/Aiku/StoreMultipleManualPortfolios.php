<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-16h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydratePortofolios;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\Platform;
use App\Models\Fulfilment\StoredItem;
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
            foreach (Arr::get($modelData, 'items') as $itemID) {
                if ($customer->is_fulfilment) {
                    /** @var StoredItem $item */
                    $item = StoredItem::find($itemID);
                } else {
                    /** @var Product $item */
                    $item = Product::find($itemID);
                }

                if ($item->portfolio()->where('customer_id', $customer->id)->exists()) {
                    continue;
                }

                StorePortfolio::make()->action(
                    customer: $customer,
                    item: $item,
                    modelData: [
                        'platform_id' => $platform->id
                    ]
                );
            }
        });

        $customerHasPlatform = CustomerHasPlatform::where('customer_id', $customer->id)
        ->where('platform_id', $platform->id)
        ->first();

        CustomerHasPlatformsHydratePortofolios::dispatch($customerHasPlatform);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array']
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
