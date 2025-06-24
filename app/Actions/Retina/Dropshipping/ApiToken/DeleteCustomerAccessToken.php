<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\ApiToken;

use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

class DeleteCustomerAccessToken extends RetinaAction
{
    use AsAction;

    public function handle(PersonalAccessToken $token): void
    {
        $tokenable = $token->tokenable;
        $token->delete();

        if ($tokenable instanceof Customer) {
            $customer = $tokenable;
            $customer->auditEvent = 'delete';
            $customer->isCustomEvent = true;
            $customer->auditCustomOld = [
                'api_token' => $token->name
            ];
            $customer->auditCustomNew = [
                'api_token' => __('Api token deleted')
            ];

            Event::dispatch(new AuditCustom($customer));
            // UserHydrateApiTokens::dispatch($user);

        }
    }


    public function asController(PersonalAccessToken $token, ActionRequest $request)
    {
        $this->initialisation($request);
        $this->handle($token);
    }
}
