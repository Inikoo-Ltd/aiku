<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\ApiToken;

use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

class StoreCustomerToken extends RetinaAction
{
    use AsAction;

    /**
     * @var \App\Models\Customer
     */
    private Customer $parent;

    public function handle(CustomerSalesChannel $customerSalesChannel): string
    {
        $plainTextToken = $customerSalesChannel->createToken(Str::random(6), ['retina'])->plainTextToken;

        $tokenParts = explode('|', $plainTextToken);
        $tokenValue = $tokenParts[1] ?? '';

        $tokenPrefix = substr($tokenValue, 0, 3);

        $tokenName = $tokenParts[0].'|'.$tokenPrefix.'...-'.$customerSalesChannel->slug;

        if (!empty($tokenPrefix)) {
            DB::table('personal_access_tokens')->where('id', $tokenParts[0])->update([
                'name' => $tokenName
            ]);
        }

        $customerSalesChannel->customer->auditEvent     = 'create';
        $customerSalesChannel->customer->isCustomEvent  = true;
        $customerSalesChannel->customer->auditCustomOld = [
            'api_token' => ''
        ];
        $customerSalesChannel->customer->auditCustomNew = [
            'api_token' => __('Api token for'. $customerSalesChannel->platform->name.' created').' ('.$tokenName.')'
        ];

        Event::dispatch(new AuditCustom($customerSalesChannel->customer));

        return $plainTextToken;
    }

    // public function afterValidator(Validator $validator): void
    // {
    //     if (!$this->customer->status) {
    //         $validator->errors()->add('customer', __('Customer is not active'));
    //     }
    // }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): string
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }

    public function jsonResponse(string $token): array
    {
        return [
            'token' => $token
        ];
    }
}
