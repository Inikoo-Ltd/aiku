<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateCustomerCreditLine extends OrgAction
{
    public static function canGrantCredit(User $user, Customer $customer): bool
    {
        return $customer->shop->type === ShopTypeEnum::B2B
            && $user->authTo("org-supervisor.{$customer->organisation_id}.accounting");
    }

    public function handle(Customer $customer, array $modelData): Customer
    {
        return UpdateCustomer::make()->action($customer, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return self::canGrantCredit($request->user(), $request->route('customer'));
    }

    public function rules(): array
    {
        return [
            'credit_limit'       => ['sometimes', 'numeric', 'min:0', 'max:1000000'],
            'payment_terms_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
