<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;

class UpdateTransactionUnits extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Transaction $transaction, array $modelData, $calculateShipping = true): Transaction
    {


        return $transaction;
    }

    public function rules(): array
    {
        return [
            'unit_quantity_ordered' => ['sometimes', 'numeric', 'min:0'],
        ];
    }


    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        dd($request->all());
        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction, $this->validatedData);
    }
}
