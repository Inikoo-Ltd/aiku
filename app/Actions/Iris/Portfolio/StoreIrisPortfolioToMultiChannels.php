<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 May 2026 19:30:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Portfolio;

use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisPortfolioToMultiChannels extends IrisAction
{
    use WithActionUpdate;

    /**
     * @var ProductCategory|null
     */
    private ?ProductCategory $productCategory = null;

    public function handle(Customer $customer, array $modelData): void
    {
        $channels = $customer->customerSalesChannels()
            ->whereIn('id', Arr::get($modelData, 'customer_sales_channel_ids'))
            ->get();

        StoreIrisPortfolioItemsToChannels::run($channels, Arr::get($modelData, 'item_id'));
    }

    public function rules(): array
    {
        return [
            'customer_sales_channel_ids'   => 'required|array|min:1',
            'customer_sales_channel_ids.*' => 'required|integer|exists:customer_sales_channels,id',
            'item_id'                      => 'required|array|min:1',
            'item_id.*'                    => 'required|integer|exists:products,id'
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->productCategory) {
            $this->set('item_id', $this->productCategory->getProducts()->pluck('id')->toArray());
        }
    }

    public function asController(ActionRequest $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $this->initialisation($request);

        $this->handle($user->customer, $this->validatedData);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->productCategory = $productCategory;
        $this->asController($request);
    }
}
