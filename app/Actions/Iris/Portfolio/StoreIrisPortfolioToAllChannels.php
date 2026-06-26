<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 May 2026 19:25:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Portfolio;

use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisPortfolioToAllChannels extends IrisAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): void
    {
        StoreIrisPortfolioItemsToChannels::run($customer->customerSalesChannels, Arr::get($modelData, 'item_id'));
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|array|min:1',
            'item_id.*' => 'required|integer|exists:products,id'
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $this->initialisation($request);

        $this->handle($user->customer, $this->validatedData);
    }
}
