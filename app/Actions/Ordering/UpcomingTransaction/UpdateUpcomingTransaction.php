<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UpcomingTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Models\Ordering\UpcomingTransaction;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateUpcomingTransaction extends OrgAction
{
    use WithActionUpdate;

    public function handle(UpcomingTransaction $upcomingTransaction, array $modelData): UpcomingTransaction
    {
        return $this->update($upcomingTransaction, $modelData);
    }

    public function rules(): array
    {
        return [
            'product_id'     => ['sometimes', 'required', 'exists:products,id'],
            'order_id'       => ['sometimes', 'nullable', 'exists:orders,id'],
            'transaction_id' => ['sometimes', 'nullable', 'exists:transactions,id'],
            'quantity'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes'          => ['sometimes', 'nullable', 'string'],
            'type'           => ['sometimes', 'required', Rule::enum(UpcomingTransactionTypeEnum::class)],
            'state'          => ['sometimes', 'required', Rule::enum(UpcomingTransactionStateEnum::class)],
        ];
    }

    public function action(UpcomingTransaction $upcomingTransaction, array $modelData): UpcomingTransaction
    {
        $this->asAction = true;
        $this->initialisationFromShop($upcomingTransaction->shop, $modelData);

        return $this->handle($upcomingTransaction, $this->validatedData);
    }

    public function asController(UpcomingTransaction $upcomingTransaction, ActionRequest $request): UpcomingTransaction
    {
        $this->initialisationFromShop($upcomingTransaction->shop, $request);

        return $this->handle($upcomingTransaction, $this->validatedData);
    }
}
