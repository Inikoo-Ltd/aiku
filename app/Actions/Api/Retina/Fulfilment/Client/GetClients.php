<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\Client;

use App\Actions\Api\Retina\Client\GetClientsAction;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetClients extends GetClientsAction
{
    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($request);

        return $this->handle($this->customerSalesChannel, $this->validatedData);
    }

}
