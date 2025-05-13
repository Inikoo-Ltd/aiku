<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders\Transaction;

use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateRetinaTransaction extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order, Transaction $transaction, array $modelData): Transaction
    {
        return UpdateTransaction::make()->action($transaction, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Order $order, Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->initialisation($request);

        return $this->handle($order, $transaction, $this->validatedData);
    }
}
