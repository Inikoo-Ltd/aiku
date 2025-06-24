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

    public function handle(Customer $customer): string
    {
        $plainTextToken = $customer->createToken(Str::random(6), ['retina'])->plainTextToken;

        $tokenParts = explode('|', $plainTextToken);
        $tokenValue = $tokenParts[1] ?? '';

        $tokenPrefix = substr($tokenValue, 0, 3);

        $tokenName = $tokenParts[0].'|'.$tokenPrefix.'...-'.$customer->slug;

        if (!empty($tokenPrefix)) {
            DB::table('personal_access_tokens')->where('id', $tokenParts[0])->update([
                'name' => $tokenName
            ]);
        }

        $customer->auditEvent     = 'create';
        $customer->isCustomEvent  = true;
        $customer->auditCustomOld = [
            'api_token' => ''
        ];
        $customer->auditCustomNew = [
            'api_token' => __('Api token created').' ('.$tokenName.')'
        ];

        Event::dispatch(new AuditCustom($customer));

        return $plainTextToken;
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->customer->status) {
            $validator->errors()->add('customer', __('Customer is not active'));
        }
    }

    public function asController(ActionRequest $request): string
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }

    public function jsonResponse(string $token): array
    {
        return [
            'token' => $token
        ];
    }
}
