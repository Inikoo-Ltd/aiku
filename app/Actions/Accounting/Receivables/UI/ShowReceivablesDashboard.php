<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Receivables\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Who owes us and how overdue: what customers owe on unpaid invoices by age, shop, currency and debtor, what
 * marketplaces still have to pay out, what our own companies owe each other, the unverified unpaid invoices
 * migrated from Aurora, and the balances customers hold with us, in the organisation's currency
 * (the group's when the whole group is read). The trend is rebuilt from the dates invoices were paid on: an
 * invoice paid later counts in full from its date to the day it was paid.
 */
class ShowReceivablesDashboard extends OrgAction
{
    use WithReceivables;

    public const int TREND_WEEKS = 52;

    public const int TOP_DEBTORS = 10;

    /**
     * @param Collection<int, Organisation> $organisations
     * @return array<string, mixed>
     */
    public function handle(Organisation|Group $parent, Collection $organisations, ?Carbon $today = null): array
    {
        $today   ??= now();
        $owed      = self::OWED_SQL;
        $converted = self::convertedSql($parent);
        $days      = self::daysOverdueSql($today);

        $rows = self::owedInvoices(DB::table('invoices'), $parent, $organisations)
            ->selectRaw("organisations.name as organisation, shops.code as shop, shops.type = ? as marketplace, currencies.code as currency, ".self::ageSql($today)." as age, invoices.as_organisation_id is not null as company, invoices.source_id is not null as legacy", [ShopTypeEnum::EXTERNAL->value])
            ->selectRaw("count(*) as invoices, sum($owed) as native, sum($converted) as amount")
            ->selectRaw("sum($converted) filter (where invoices.is_cash_on_delivery) as cod, sum($converted) filter (where invoices.is_pastpay) as pastpay, sum($converted) filter (where $days > 365) as over_year")
            ->groupByRaw('1, 2, 3, 4, 5, 6, 7')
            ->get();

        [$legacy, $rows]            = $rows->partition(fn (object $row) => $row->legacy);
        [$marketplaces, $customers] = $rows->partition(fn (object $row) => $row->marketplace);
        $companies                  = $customers->where('company', true);
        if ($parent instanceof Group) {
            $customers = $customers->where('company', false);
        }
        $sum                        = fn (Collection $group, string $field = 'amount') => round((float) $group->sum($field), 2);
        $ranked                     = fn (Collection $group, string $key) => $group->groupBy($key)
            ->map(fn (Collection $group, string $label) => ['key' => $label, 'label' => $label, 'amount' => $sum($group), 'native' => $sum($group, 'native'), 'invoices' => (int) $group->sum('invoices')])
            ->sortByDesc('amount')->values()->all();

        $debtors = self::owedInvoices(DB::table('invoices'), $parent, $organisations, 'customers')
            ->leftJoin('fulfilments', 'fulfilments.shop_id', 'shops.id')
            ->select(['customers.id', 'customers.name', 'customers.reference', 'customers.slug', 'customers.as_organisation_id', 'shops.code as shop_code', 'shops.slug as shop_slug', 'shops.type as shop_type', 'fulfilments.slug as fulfilment_slug', 'organisations.slug as organisation_slug', 'organisations.code as organisation_code'])
            ->selectRaw("sum($converted) as amount, count(*) as invoices, min(invoices.date) as oldest, max($days) as days_overdue")
            ->groupBy(['customers.id', 'shops.id', 'fulfilments.id', 'organisations.id'])
            ->orderByDesc('amount')
            ->limit(self::TOP_DEBTORS)
            ->get()
            ->map(fn (object $row) => [
                'id'                => $row->id,
                'name'              => $row->name,
                'reference'         => $row->reference,
                'shop_code'         => $row->shop_code,
                'organisation_code' => $row->organisation_code,
                'company'           => $row->as_organisation_id !== null,
                'amount'            => round((float) $row->amount, 2),
                'invoices'          => (int) $row->invoices,
                'oldest'            => $row->oldest,
                'days_overdue'      => (int) $row->days_overdue,
                'route'             => self::customerRoute($row),
            ])->all();

        $balances = self::customerBalances($organisations)
            ->selectRaw('customers.balance > 0 as credit, currencies.code as currency, count(*) as customers, sum(customers.balance) as native, sum('.self::convertedBalanceSql($parent).') as amount')
            ->groupByRaw('1, 2')
            ->toBase()
            ->get();
        $side = fn (bool $credit) => [
            'amount'     => abs($sum($balances->where('credit', $credit))),
            'customers'  => (int) $balances->where('credit', $credit)->sum('customers'),
            'currencies' => $balances->where('credit', $credit)->sortByDesc(fn (object $row) => abs((float) $row->amount))
                ->map(fn (object $row) => ['currency' => $row->currency, 'native' => abs(round((float) $row->native, 2)), 'converted' => $row->amount === null ? null : abs(round((float) $row->amount, 2)), 'customers' => (int) $row->customers])
                ->values()->all(),
        ];

        return [
            'total'         => [
                'amount'    => $sum($customers),
                'invoices'  => (int) $customers->sum('invoices'),
                'debtors'   => (int) self::owedInvoices(DB::table('invoices'), $parent, $organisations, 'customers')->distinct()->count('invoices.customer_id'),
                'overdue'   => $sum($customers->where('age', '!=', 'not_due')),
                'over_year' => $sum($customers, 'over_year'),
                'cod'       => $sum($customers, 'cod'),
                'pastpay'   => $sum($customers, 'pastpay'),
            ],
            'ageing'        => collect(self::AGES)->map(fn (string $age) => ['key' => $age, 'amount' => $sum($customers->where('age', $age)), 'invoices' => (int) $customers->where('age', $age)->sum('invoices')])->all(),
            'shops'         => $ranked($customers, 'shop'),
            'currencies'    => $ranked($customers, 'currency'),
            'organisations' => $parent instanceof Group ? $ranked($customers, 'organisation') : [],
            'debtors'       => $debtors,
            'companies'     => ['amount' => $sum($companies), 'invoices' => (int) $companies->sum('invoices')],
            'legacy'        => ['amount' => $sum($legacy), 'invoices' => (int) $legacy->sum('invoices'), 'organisations' => $parent instanceof Group ? $ranked($legacy, 'organisation') : []],
            'marketplaces'  => ['amount' => $sum($marketplaces), 'invoices' => (int) $marketplaces->sum('invoices'), 'shops' => $ranked($marketplaces, 'shop')],
            'balances'      => ['credit' => $side(true), 'debt' => $side(false)],
            'trend'         => $this->trend($parent, $organisations, $today),
        ];
    }

    /**
     * What customers owed at the end of each of the last weeks: what is owed today that was already invoiced,
     * plus the invoices paid after that day, in full.
     *
     * @param Collection<int, Organisation> $organisations
     * @return array{dates: array<int, string>, totals: array<int, float>}
     */
    private function trend(Organisation|Group $parent, Collection $organisations, Carbon $today): array
    {
        $weeks    = self::TREND_WEEKS;
        $day      = "date '{$today->toDateString()}'";
        $exchange = 'invoices.'.($parent instanceof Group ? 'grp_exchange' : 'org_exchange');

        $open = self::owedInvoices(DB::table('invoices'), $parent, $organisations, 'customers')
            ->selectRaw("least(greatest(($day - invoices.date::date) / 7, 0), $weeks) as since, 0 as until, sum(".self::convertedSql($parent).') as amount')
            ->groupByRaw('1')
            ->get();

        $paidLater = DB::table('invoices')
            ->join('shops', 'shops.id', 'invoices.shop_id')
            ->whereIn('invoices.organisation_id', $organisations->pluck('id'))
            ->where('invoices.pay_status', InvoicePayStatusEnum::PAID->value)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE->value)
            ->where('invoices.in_process', false)
            ->whereNull('invoices.deleted_at')
            ->where('shops.type', '!=', ShopTypeEnum::EXTERNAL->value)
            ->whereNull('invoices.source_id')
            ->when($parent instanceof Group, fn ($query) => $query->whereNull('invoices.as_organisation_id'))
            ->where('invoices.paid_at', '>=', $today->copy()->subWeeks($weeks)->startOfDay())
            ->whereRaw('invoices.paid_at::date > invoices.date::date')
            ->selectRaw("least(($day - invoices.date::date) / 7, $weeks) as since, greatest(($day - invoices.paid_at::date) / 7 + 1, 0) as until, sum(invoices.total_amount * $exchange) as amount")
            ->groupByRaw('1, 2')
            ->get();

        $spans  = $open->concat($paidLater);
        $dates  = [];
        $totals = [];
        for ($week = $weeks; $week >= 0; $week--) {
            $dates[]  = $today->copy()->subWeeks($week)->toDateString();
            $totals[] = round((float) $spans->filter(fn (object $span) => $span->until <= $week && $week <= $span->since)->sum('amount'), 2);
        }

        return ['dates' => $dates, 'totals' => $totals];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        return $this->handle($this->readOrganisation($organisation, $request), $this->readOrganisations);
    }

    /**
     * @return array<string, mixed>
     */
    public function inGroup(ActionRequest $request): array
    {
        return $this->handle($this->readGroup($request), $this->readOrganisations);
    }

    public function htmlResponse(array $receivables, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/ReceivablesDashboard',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => __('Receivables'),
                'pageHead'      => [
                    'title'         => __('Receivables'),
                    'icon'          => ['fal', 'fa-hand-holding-usd'],
                    'subNavigation' => $this->subNavigation(),
                ],
                'organisation'  => $this->organisationSlug,
                'organisations' => $this->organisationOptions(),
                'isGroup'       => $this->isGroup(),
                'currency'      => $this->parent()->currency->code,
                'routes'        => $this->sectionRoutes(),
                'receivables'   => $receivables,
            ]
        );
    }
}
