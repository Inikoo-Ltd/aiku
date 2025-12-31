<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: 31-12-2025
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\CreditBalanceNotification;

use App\Actions\OrgAction;
use App\Actions\Comms\Email\SendCreditBalanceEmailToCustomer;
use App\Actions\Comms\Email\SendCreditBalanceEmailToUser;
use App\Models\CRM\Customer;

class ProcessCreditBalanceNotification extends OrgAction
{
    public function handle(Customer $customer)
    {
        // get last 2 credit transactions
        $creditBalances = $customer->creditTransactions()->orderBy('id', 'desc')->limit(2)->get();
        $previousCreditBalance = null;
        $currentCreditBalance = null;
        if ($creditBalances->count() === 1) {
            $previousCreditBalance = null;
            $currentCreditBalance = $creditBalances->first();
        } else {
            $previousCreditBalance = $creditBalances->last();
            $currentCreditBalance = $creditBalances->first();
        }
        $additionalDataForCustomer = [
            'previous_balance' => $previousCreditBalance?->running_amount ?? 0,
            'balance' => $currentCreditBalance->running_amount,
        ];
        $additionalDataForUser = [
            'previous_balance' => $previousCreditBalance?->running_amount ?? 0,
            'balance' => $currentCreditBalance->running_amount,
            'customer_name' => $customer->name,
            // 'customer_link' => route
        ];

        SendCreditBalanceEmailToCustomer::dispatch($customer, $additionalDataForCustomer);
        SendCreditBalanceEmailToUser::dispatch($customer, $additionalDataForUser);
    }
}
