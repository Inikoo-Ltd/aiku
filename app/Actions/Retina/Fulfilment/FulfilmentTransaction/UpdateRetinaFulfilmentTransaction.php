<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Jan 2025 02:07:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\FulfilmentTransaction;

use App\Actions\Fulfilment\FulfilmentTransaction\UpdateFulfilmentTransaction;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\FulfilmentTransaction;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaFulfilmentTransaction extends RetinaAction
{
    public function handle(FulfilmentTransaction $fulfilmentTransaction, array $modelData): FulfilmentTransaction
    {
        return UpdateFulfilmentTransaction::run($fulfilmentTransaction, $modelData);
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(FulfilmentTransaction $fulfilmentTransaction, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($fulfilmentTransaction, $this->validatedData);
    }

    public function action(FulfilmentTransaction $fulfilmentTransaction, array $modelData): FulfilmentTransaction
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($fulfilmentTransaction->fulfilmentCustomer, $modelData);

        return $this->handle($fulfilmentTransaction, $modelData);
    }
}
