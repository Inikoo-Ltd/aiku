<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UpcomingTransaction;

use App\Actions\OrgAction;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\UpcomingTransaction;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreUpcomingTransaction extends OrgAction
{
    public function handle(Customer $customer, array $modelData): UpcomingTransaction
    {
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->shop_id);

        /** @var UpcomingTransaction $upcomingTransaction */
        $upcomingTransaction = $customer->upcomingTransactions()->create($modelData);

        return $upcomingTransaction;
    }

    public function rules(): array
    {
        return [
            'product_id'     => ['required', 'exists:products,id'],
            'order_id'       => ['sometimes', 'nullable', 'exists:orders,id'],
            'transaction_id' => ['sometimes', 'nullable', 'exists:transactions,id'],
            'quantity'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'public_notes'   => ['sometimes', 'nullable', 'string'],
            'private_notes'  => ['sometimes', 'nullable', 'string'],
            'type'           => ['required', Rule::enum(UpcomingTransactionTypeEnum::class)],
            'state'          => ['sometimes', Rule::enum(UpcomingTransactionStateEnum::class)],
        ];
    }

    public function action(Customer $customer, array $modelData): UpcomingTransaction
    {
        $this->asAction = true;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    public function asController(Customer $customer, ActionRequest $request): UpcomingTransaction
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }
}
