<?php

namespace App\Actions\CRM\Customer\UI;

use App\Actions\CRM\Customer\UpdateCustomerCreditLine;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\User;

trait WithCustomerPagePermissions
{
    /**
     * @return array{edit: bool, edit_customer: bool, edit_address: bool, edit_subscriptions: bool, edit_balance: bool, edit_gift_opt_out: bool}
     */
    protected function getCustomerPagePermissions(?User $user, Customer $customer): array
    {
        if (!$user) {
            return [
                'edit'               => false,
                'edit_customer'      => false,
                'edit_address'       => false,
                'edit_subscriptions' => false,
                'edit_balance'       => false,
                'edit_gift_opt_out'  => false,
            ];
        }

        $crmEdit = "crm.$customer->shop_id.edit";

        return [
            'edit'               => $user->authTo($crmEdit),
            'edit_customer'      => $user->authTo($crmEdit) || UpdateCustomerCreditLine::canGrantCredit($user, $customer),
            'edit_address'       => $user->authTo([$crmEdit, "orders.$customer->shop_id.edit"]),
            'edit_subscriptions' => $user->authTo([$crmEdit, "marketing.$customer->shop_id.edit", "supervisor-marketing.$customer->shop_id"]),
            'edit_balance'       => $user->authTo([$crmEdit, "accounting.$customer->organisation_id.edit"]),
            'edit_gift_opt_out'  => $user->authTo([$crmEdit, "accounting.$customer->organisation_id.edit", "orders.$customer->shop_id.edit"]),
        ];
    }
}
