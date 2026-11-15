<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\UI;

use App\Actions\Accounting\BankPayee\ClassifyBankOutgoing;
use App\Enums\Accounting\BankPayee\BankPayeeCategoryEnum;
use Illuminate\Support\Collection;

/**
 * What the organisation's banks paid out in a period: by what it was for, which payees still wait to be
 * classified, the trend against the period before, and who and where it went. Conversions between our own
 * balances are not money paid: they are counted apart.
 */
class ShowBankOutgoingDashboard extends ShowBankIncomingDashboard
{
    protected function index(): IndexBankIncoming|IndexBankOutgoing
    {
        return IndexBankOutgoing::make();
    }

    protected function slug(): string
    {
        return 'bank_outgoing';
    }

    protected function title(): string
    {
        return __('Bank outgoing');
    }

    protected function placedSql(): string
    {
        return "case
                when bank_transactions.provider_type = '".ClassifyBankOutgoing::OWN_BALANCE_TYPE."' then 'own'
                ".($this->groupIndex !== null ? "when bank_transactions.out_category = '".BankPayeeCategoryEnum::OWN_COMPANIES->value."' then 'own'" : '')."
                when bank_transactions.out_category is null then 'unread'
                when bank_transactions.out_category = '".ClassifyBankOutgoing::NEEDS_PERSON."' then 'waiting'
                when (select bank_payees.source from bank_payees where bank_payees.group_id = bank_transactions.group_id and bank_payees.key = bank_transactions.payee_key) = 'person' then 'hand'
                else 'auto' end";
    }

    protected function providerSql(): string
    {
        return "case when bank_transactions.payee_key is not null and bank_transactions.out_category <> '".ClassifyBankOutgoing::NEEDS_PERSON."' then bank_transactions.payee_key end";
    }

    protected function reasonSql(): string
    {
        return "case when coalesce(bank_transactions.out_category, '".ClassifyBankOutgoing::NEEDS_PERSON."') = '".ClassifyBankOutgoing::NEEDS_PERSON."' then coalesce(bank_transactions.payee_key, '?') end";
    }

    protected function providerLabels(): ?array
    {
        return null;
    }

    protected function payeeLabels(Collection $rows, string $key): ?array
    {
        return $rows->whereNotNull($key)->whereNotNull('payee_name')->pluck('payee_name', $key)->all();
    }

    protected function extraProps(): array
    {
        $colors = ['suppliers' => '#1f845a', 'own_companies' => '#3b82f6', 'staff' => '#c0399f', 'tax' => '#8b5cf6', 'services' => '#06b6d4', 'customer_refunds' => '#84cc16', 'bank_fees' => '#ef4444', 'other' => '#9ca3af', 'needs_person' => '#f59e0b'];

        return [
            'direction'   => 'out',
            'ownCompaniesApart' => $this->groupIndex !== null,
            'sectionMeta' => collect(IndexBankOutgoing::sections())->map(fn (array $section, string $key) => ['key' => $key, 'label' => $section[0], 'color' => $colors[$key]])->values()->all(),
        ];
    }
}
