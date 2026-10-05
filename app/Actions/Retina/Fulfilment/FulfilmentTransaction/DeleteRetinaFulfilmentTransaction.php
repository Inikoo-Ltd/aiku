<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Jan 2025 02:07:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\FulfilmentTransaction;

use App\Actions\Fulfilment\FulfilmentTransaction\DeleteFulfilmentTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Models\Fulfilment\FulfilmentTransaction;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaFulfilmentTransaction extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels;

    public function handle(FulfilmentTransaction $fulfilmentTransaction): void
    {
        DeleteFulfilmentTransaction::run($fulfilmentTransaction);
    }

    public function asController(FulfilmentTransaction $fulfilmentTransaction, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($fulfilmentTransaction);
    }

    public function action(FulfilmentTransaction $fulfilmentTransaction): void
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($fulfilmentTransaction->fulfilmentCustomer, []);
        $this->handle($fulfilmentTransaction);
    }
}
