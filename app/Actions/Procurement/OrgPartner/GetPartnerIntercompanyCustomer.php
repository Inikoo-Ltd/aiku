<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\CRM\Customer;
use App\Models\Procurement\OrgPartner;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerIntercompanyCustomer
{
    use AsObject;

    public function handle(OrgPartner $orgPartner, int $shopId): ?Customer
    {
        $customerId = data_get($orgPartner->data, "intercompany_customers.$shopId");
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                return $customer;
            }
        }

        $customer = Customer::where('shop_id', $shopId)
            ->whereRaw(
                "lower(regexp_replace(coalesce(company_name, name) COLLATE \"C\", '[^a-zA-Z0-9]', '', 'g')) = ?",
                [$this->normalise($orgPartner->organisation->name)]
            )
            ->orderBy('id')
            ->first();

        if ($customer) {
            $orgPartner->update([
                'data' => array_replace_recursive($orgPartner->data ?? [], [
                    'intercompany_customers' => [$shopId => $customer->id],
                ]),
            ]);
        }

        return $customer;
    }

    private function normalise(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    }
}
