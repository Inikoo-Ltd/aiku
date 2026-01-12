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

        $previewAmount = $currentCreditBalance?->amount ?? 0;
        $currencySymbol = $currentCreditBalance?->currency?->symbol ?? '$';
        $amountColor = $previewAmount < 0 ? '#E74C3C' : '#27AE60';
        $previewAmountHtml = '<span style="color: ' . $amountColor . '; font-weight: 600;">' . ($previewAmount < 0 ? '-' : '') . $currencySymbol . number_format(abs($previewAmount), 2) . '</span>';

        $additionalDataForUser = [
            'previous_balance' => $previousCreditBalance?->running_amount ?? 0,
            'balance' => $currentCreditBalance->running_amount,
            'customer_name' => $customer->name,
            'customer_link' => $customer->shop->fulfilment
                ? route('grp.org.fulfilments.show.crm.customers.show', [
                    $customer->organisation->slug,
                    $customer->shop->fulfilment->slug,
                    $customer->fulfilmentCustomer->slug
                ])
                : route('grp.org.shops.show.crm.customers.show', [
                    $customer->organisation->slug,
                    $customer->shop->slug,
                    $customer->slug
                ]),
            'payment_type' => $currentCreditBalance?->type?->label() ?? 'N/A',
            'payment_note' => $currentCreditBalance?->notes ?? 'N/A',
            'payment_balance_preview' => 'test This will show Preview',
            'payment_reason' => $currentCreditBalance?->reason?->label() ?? 'N/A',
            // TODO: make sure this path
            'payment_balance_preview' => '<span style="color: #333;">' . $currencySymbol . number_format(abs($previousCreditBalance?->running_amount ?? 0), 2) . '</span> <span style="color: #dc3545;">- ' . $previewAmountHtml . '</span> <span style="color: #dc3545; margin: 0 8px;">→</span> <span style="color: #333; font-weight: bold;">' . $currencySymbol . number_format(abs($currentCreditBalance->running_amount), 2) . '</span>',
            'preview_amount' => $previewAmountHtml,
        ];

        SendCreditBalanceEmailToCustomer::dispatch($customer, $additionalDataForCustomer);
        SendCreditBalanceEmailToUser::dispatch($customer, $additionalDataForUser);
    }
}
