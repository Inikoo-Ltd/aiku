<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 11:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\ProfitAndLoss;

use App\Actions\Accounting\BankPayee\ClassifyBankOutgoing;
use App\Actions\OrgAction;
use App\Enums\Accounting\BankPayee\BankPayeeCategoryEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Management profit and loss by month, not a statutory one, in the organisation's currency (the group's when the
 * whole group is read), each month beside the same month a year before.
 *
 * Sales are the net of invoices and refunds. Cost of goods is the official (FIFO) value of the stock picked, net of
 * cancelled and returned picks. Fees and other costs are read from the connected banks: the banks' own fees, card
 * processor fees of reconciled payouts, marketplace commissions, and what was paid out by category. Payments to
 * suppliers are stock (already in cost of goods), refunds to customers are already refund invoices, payments between
 * our companies are not costs, and tax paid is shown apart: none of them reduce the result. A month with no bank line
 * has no known costs: those lines are null, never 0.
 *
 * Read for the group, sales between our companies and the cost of the stock picked for them are left out. Picks
 * imported from Aurora have no delivery note, so stock picked for our companies before aiku stays in. The buying
 * company's later cost of goods carries the price it paid us, so the group's margin is understated by that markup.
 */
class GetProfitAndLoss extends OrgAction
{
    public const array COGS_TYPES = [
        OrgStockMovementTypeEnum::PICKED,
        OrgStockMovementTypeEnum::CANCEL_PICKED,
        OrgStockMovementTypeEnum::RETURN_PICKED,
        OrgStockMovementTypeEnum::CANCEL_RETURN_PICKED,
        OrgStockMovementTypeEnum::RETURN_DISPATCH,
    ];

    public const array COST_LINES = ['staff', 'services', 'other', 'unclassified'];

    public const array FEE_LINES = ['bank_fees', 'provider_fees', 'marketplace_fees'];

    /**
     * @param Collection<int, Organisation> $organisations
     * @return array{currency: string, months: array<int, array<string, mixed>>, total: array<string, mixed>}
     */
    public function handle(Organisation|Group $parent, Collection $organisations, Carbon $from, Carbon $to): array
    {
        $from            = $from->copy()->startOfMonth();
        $to              = $to->copy()->endOfMonth();
        $organisationIds = $organisations->pluck('id')->all();
        $isGroup         = $parent instanceof Group;

        $sales = $this->sales($organisationIds, $isGroup, $from->copy()->subYear(), $to);
        $cogs  = $this->costOfGoods($organisationIds, $isGroup, $from->copy()->subYear(), $to);
        $bank  = $this->bank($organisationIds, (int) $parent->currency_id, $from->copy()->subYear(), $to);

        $months = [];
        for ($month = $from->copy(); $month->lte($to); $month->addMonth()) {
            $key      = $month->format('Y-m');
            $lastYear = $month->copy()->subYear()->format('Y-m');
            $months[] = array_merge(
                ['month' => $key],
                $this->lines($sales->get($key), $cogs->get($key), $bank->get($key)),
                ['last_year' => $this->lines($sales->get($lastYear), $cogs->get($lastYear), $bank->get($lastYear))]
            );
        }

        return [
            'currency' => $parent->currency->code,
            'months'   => $months,
            'total'    => array_merge($this->total($months), ['last_year' => $this->total(array_column($months, 'last_year'))]),
        ];
    }

    /**
     * @return array<string, float|int|null>
     */
    private function lines(?float $sales, ?float $costOfGoods, ?object $bank): array
    {
        $sales       = round($sales ?? 0, 2);
        $costOfGoods = round($costOfGoods ?? 0, 2);
        $lines       = [
            'sales'         => $sales,
            'cost_of_goods' => $costOfGoods,
            'gross_margin'  => round($sales - $costOfGoods, 2),
        ];

        foreach (array_merge(self::FEE_LINES, self::COST_LINES, ['tax_paid']) as $line) {
            $lines[$line] = $bank ? round((float) $bank->{$line}, 2) : null;
        }
        $lines['provider_fees_unknown'] = $bank ? (int) $bank->provider_fees_unknown : 0;
        $lines['result']                = $bank ? round($lines['gross_margin'] - collect(array_merge(self::FEE_LINES, self::COST_LINES))->sum(fn (string $line) => $lines[$line]), 2) : null;

        return $lines;
    }

    /**
     * Months with no bank line add nothing to the costs, and the period's result is only known when every month's is.
     *
     * @param array<int, array<string, mixed>> $months
     * @return array<string, float|int|null>
     */
    private function total(array $months): array
    {
        $total = [];
        foreach (array_keys(collect($months)->first() ?? []) as $line) {
            if (in_array($line, ['month', 'last_year'], true)) {
                continue;
            }
            $known        = collect($months)->pluck($line)->filter(fn ($value) => $value !== null);
            $total[$line] = $known->isEmpty() ? null : round($known->sum(), 2);
        }
        if (collect($months)->contains(fn (array $month) => $month['result'] === null)) {
            $total['result'] = null;
        }

        return $total;
    }

    /**
     * @param array<int, int> $organisationIds
     * @return Collection<string, float>
     */
    private function sales(array $organisationIds, bool $isGroup, Carbon $from, Carbon $to): Collection
    {
        return DB::connection('aiku_no_sticky')->table('invoices')
            ->whereIn('organisation_id', $organisationIds)
            ->whereNull('deleted_at')
            ->where('in_process', false)
            ->whereBetween('date', [$from, $to])
            ->when($isGroup, fn ($query) => $query->whereNull('as_organisation_id'))
            ->selectRaw("to_char(date, 'YYYY-MM') as month, sum(".($isGroup ? 'grp_net_amount' : 'org_net_amount').') as amount')
            ->groupBy('month')
            ->pluck('amount', 'month')
            ->map(fn ($amount) => (float) $amount);
    }

    /**
     * Closed months come from cost_of_goods_months (worked out overnight by HydrateCostOfGoodsMonths); the current month,
     * and any month the table does not hold for every organisation, are read live from the stock movements.
     *
     * @param array<int, int> $organisationIds
     * @return Collection<string, float>
     */
    private function costOfGoods(array $organisationIds, bool $isGroup, Carbon $from, Carbon $to): Collection
    {
        $column   = $isGroup ? 'grp_amount_external' : 'org_amount';
        $months   = [];
        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }
        $closed   = array_values(array_filter($months, fn (string $month) => $month < now()->format('Y-m')));
        $stored   = DB::connection('aiku_no_sticky')->table('cost_of_goods_months')
            ->whereIn('organisation_id', $organisationIds)
            ->whereIn('month', $closed)
            ->get(['organisation_id', 'month', $column.' as amount']);
        $complete = $stored->groupBy('month')->filter(fn (Collection $rows) => $rows->count() === count($organisationIds))->keys()->all();
        $liveFrom = collect($months)->first(fn (string $month) => !in_array($month, $complete, true));

        $amounts = $stored->whereIn('month', $complete)->filter(fn (object $row) => $liveFrom === null || $row->month < $liveFrom)
            ->groupBy('month')->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        if ($liveFrom !== null) {
            self::costOfGoodsQuery($organisationIds, Carbon::parse($liveFrom.'-01')->startOfMonth(), $to)
                ->get()
                ->groupBy('month')
                ->each(fn (Collection $rows, string $month) => $amounts->put($month, (float) $rows->sum($isGroup ? 'grp_amount_external' : 'org_amount')));
        }

        return $amounts;
    }

    /**
     * Cost of goods per organisation and month: the picks net of cancelled and returned ones, in the organisation's
     * currency, and in the group's currency without the stock picked for our own companies.
     *
     * @param array<int, int> $organisationIds
     */
    public static function costOfGoodsQuery(array $organisationIds, Carbon $from, Carbon $to): \Illuminate\Database\Query\Builder
    {
        $ownCompanies = "select delivery_notes.id from delivery_notes join customers on customers.id = delivery_notes.customer_id where customers.as_organisation_id is not null";

        return DB::connection('aiku_no_sticky')->table('org_stock_movements')
            ->whereIn('org_stock_movements.organisation_id', $organisationIds)
            ->whereIn('org_stock_movements.type', array_map(fn (OrgStockMovementTypeEnum $type) => $type->value, self::COGS_TYPES))
            ->whereBetween('org_stock_movements.date', [$from, $to])
            ->selectRaw("org_stock_movements.organisation_id, to_char(org_stock_movements.date, 'YYYY-MM') as month")
            ->selectRaw("-sum(org_stock_movements.org_amount) as org_amount, -coalesce(sum(org_stock_movements.grp_amount) filter (where (org_stock_movements.parent_type = 'DeliveryNote' and org_stock_movements.parent_id in ($ownCompanies)) is not true), 0) as grp_amount_external")
            ->groupByRaw('1, 2');
    }

    /**
     * Each line converted at the last rate known on the day it was booked. The bank's own fee on a line is counted
     * once, as a bank fee: an outgoing line's amount includes it, so it is taken out of what the line paid for.
     *
     * @param array<int, int> $organisationIds
     * @return Collection<string, object>
     */
    private function bank(array $organisationIds, int $target, Carbon $from, Carbon $to): Collection
    {
        $pivot     = (int) Currency::where('code', config('app.currency_exchange.pivot'))->value('id');
        $rate      = fn (string $currencyId) => "case when $currencyId = $pivot then 1 else (select ce.exchange from currency_exchanges ce where ce.currency_id = $currencyId and ce.date <= bank_transactions.booked_at::date order by ce.date desc limit 1) end";
        $converted = fn (string $amount) => "case when bank_transactions.currency_id = $target then $amount else $amount * ({$rate((string) $target)}) / nullif(({$rate('bank_transactions.currency_id')}), 0) end";

        $fee      = 'coalesce(bank_transactions.fee_amount, 0)';
        $paid     = "(-bank_transactions.amount - $fee)";
        $category = "case when bank_transactions.provider_type = '".ClassifyBankOutgoing::OWN_BALANCE_TYPE."' then null
            when bank_transactions.out_category in ('".BankPayeeCategoryEnum::STAFF->value."', '".BankPayeeCategoryEnum::SERVICES->value."', '".BankPayeeCategoryEnum::OTHER->value."', '".BankPayeeCategoryEnum::TAX->value."', '".BankPayeeCategoryEnum::BANK_FEES->value."') then bank_transactions.out_category
            when bank_transactions.out_category is null or bank_transactions.out_category = '".ClassifyBankOutgoing::NEEDS_PERSON."' then 'unclassified' end";
        $out      = fn (string $line) => "coalesce(sum({$converted($paid)}) filter (where bank_transactions.amount < 0 and $category = '$line'), 0)";
        $payout   = "bank_transactions.match_state = '".BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT->value."'";

        return DB::connection('aiku_no_sticky')->table('bank_transactions')
            ->whereIn('organisation_id', $organisationIds)
            ->where('status', BankTransactionStatusEnum::BOOKED)
            ->whereBetween('booked_at', [$from, $to])
            ->selectRaw("to_char(booked_at, 'YYYY-MM') as month")
            ->selectRaw("{$out('bank_fees')} + coalesce(sum({$converted($fee)}), 0) as bank_fees")
            ->selectRaw("coalesce(sum({$converted("(bank_transactions.match_data->>'processor_fees')::numeric")}) filter (where $payout), 0) as provider_fees")
            ->selectRaw("count(*) filter (where $payout and bank_transactions.match_data->>'reconciled_at' is null) as provider_fees_unknown")
            ->selectRaw("coalesce(sum({$converted("(bank_transactions.match_data->>'commission')::numeric")}) filter (where bank_transactions.match_state = '".BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE->value."'), 0) as marketplace_fees")
            ->selectRaw("{$out('staff')} as staff, {$out('services')} as services, {$out('other')} as other, {$out('unclassified')} as unclassified, {$out(BankPayeeCategoryEnum::TAX->value)} as tax_paid")
            ->groupBy('month')
            ->get()
            ->keyBy('month');
    }
}
