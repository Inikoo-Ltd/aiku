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

        // Extract currency information
        $currency = $currentCreditBalance?->currency;
        $currencySymbol = $currency?->symbol ?? '$';
        $fractionDigit = $currency?->fraction_digit ?? 2;

        // Extract and calculate amounts
        $currentAmount = $currentCreditBalance?->amount ?? 0;
        $previousAmount = $previousCreditBalance?->running_amount ?? 0;
        $runningAmount = $currentCreditBalance->running_amount;

        // Determine styling based on amount
        $amountColor = $currentAmount < 0 ? '#E74C3C' : '#27AE60';
        $amountSign = $currentAmount < 0 ? '-' : '';
        $changeSymbol = $currentAmount > 0 ? '+' : '-';

        // Format amounts
        $formattedCurrentAmount = number_format(abs($currentAmount), $fractionDigit);
        $formattedPreviousAmount = number_format(abs($previousAmount), $fractionDigit);
        $formattedRunningAmount = number_format(abs($runningAmount), $fractionDigit);

        // Build HTML components
        $previewAmountHtml = '<span style="color: ' . $amountColor . '; font-weight: 600;">'
            . $amountSign . $currencySymbol . $formattedCurrentAmount
            . '</span>';

        $previewBalanceAmountHtml = '<span style="color: ' . $amountColor . '; font-weight: 600;">'
            . $currencySymbol . $formattedCurrentAmount
            . '</span>';

        $paymentBalancePreview = '<span style="color: #333;">'
            . $currencySymbol . $formattedPreviousAmount
            . '</span> '
            . $changeSymbol . ' '
            . $previewBalanceAmountHtml
            . ' <span style="margin: 0 8px;">→</span> '
            . '<span style="color: #333; font-weight: bold;">'
            . $currencySymbol . $formattedRunningAmount
            . '</span>';

        // Generate customer link
        $customerLink = $customer->shop->fulfilment
            ? route('grp.org.fulfilments.show.crm.customers.show', [
                $customer->organisation->slug,
                $customer->shop->fulfilment->slug,
                $customer->fulfilmentCustomer->slug
            ])
            : route('grp.org.shops.show.crm.customers.show', [
                $customer->organisation->slug,
                $customer->shop->slug,
                $customer->slug
            ]);

        $additionalDataForUser = [
            'balance' => $currentCreditBalance->running_amount,
            'customer_name' => $customer->name,
            'customer_link' => $customerLink,
            'payment_type' => $currentCreditBalance?->type?->label() ?? 'N/A',
            'payment_note' => $currentCreditBalance?->notes ?? 'N/A',
            'payment_reason' => $currentCreditBalance?->reason?->label() ?? 'N/A',
            'payment_balance_preview' => $paymentBalancePreview,
            'preview_amount' => $previewAmountHtml,
        ];

        $additionalDataForCustomer = [
            'payment_type' => $currentCreditBalance?->type?->label() ?? 'N/A',
            'payment_reason' => $currentCreditBalance?->reason?->label() ?? 'N/A',
            'payment_note' => $currentCreditBalance?->notes ?? 'N/A',
            'payment_balance_preview' => $paymentBalancePreview,
        ];

        SendCreditBalanceEmailToCustomer::dispatch($customer, $additionalDataForCustomer);
        SendCreditBalanceEmailToUser::dispatch($customer, $additionalDataForUser);
    }
}
