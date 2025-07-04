<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 10:39:07 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina;

use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCheckoutComTokenToPayOrder extends RetinaAction
{
    use AsAction;


    public function handle()
    {



        return [
            'status'        => 'success',
            'token'         => 'checkout_com_token',
            'amount_to_pay' => 22,
            'currency_code' => 'XX'
        ];
    }

    public function asController(ActionRequest $request): Customer
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }

}