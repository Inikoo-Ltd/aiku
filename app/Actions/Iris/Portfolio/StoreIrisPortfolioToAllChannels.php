<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 May 2026 19:25:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Portfolio;

use App\Actions\CRM\ShoppingAssistant\RecordShoppingAssistantBasketAdd;
use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisPortfolioToAllChannels extends IrisAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    /**
     * @return array<int, int> the products newly added to at least one channel
     */
    public function handle(Customer $customer, array $modelData): array
    {
        $channels = $customer->customerSalesChannels()
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->get();

        return StoreIrisPortfolioItemsToChannels::run($channels, Arr::get($modelData, 'item_id'));
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

        RecordShoppingAssistantBasketAdd::afterResponse($this->website, $user->customer, array_map(
            fn (int $productId) => ['product_id' => $productId, 'transaction_id' => null, 'quantity' => 1],
            $this->handle($user->customer, $this->validatedData)
        ));
    }
}
