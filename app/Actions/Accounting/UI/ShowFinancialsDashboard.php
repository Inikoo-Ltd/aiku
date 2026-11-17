<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\UI;

use App\Actions\Accounting\BankBalance\UI\ShowCash;
use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\Accounting\BankTransaction\UI\IndexBankOutgoing;
use App\Actions\Accounting\BankTransaction\UI\ShowBankIncomingDashboard;
use App\Actions\Accounting\BankTransaction\UI\ShowBankOutgoingDashboard;
use App\Actions\Accounting\BankTransaction\UI\ShowPayouts;
use App\Actions\Accounting\Payables\GetPayables;
use App\Actions\Accounting\Receivables\UI\ShowReceivablesDashboard;
use App\Actions\Accounting\Receivables\UI\WithReceivables;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Financials landing page, in the group's currency, built from the other Financials pages: the cash the connected
 * banks hold, money in and out of the banks in the last 90 days against the 90 before, what waits for a person,
 * receivables, payables and what payment providers hold.
 *
 * The 30/60/90 day forecast starts from that cash and adds, each counted once:
 * - receivables (customers and marketplaces, not our own companies nor invoices migrated from Aurora) on their due
 *   date; overdue ones are shown apart and not counted, as nobody can say when they will be paid;
 * - what Checkout and PastPay hold, in the first 30 days (cash on delivery and marketplaces are left out: what they
 *   hold is unpaid invoices, already in receivables);
 * - the unpaid part of orders submitted and not invoiced yet, due a week (to dispatch) plus the customer's payment terms;
 * and takes away the payables, all in the first 30 days: no payable records a due date, so they are treated as owed now.
 */
class ShowFinancialsDashboard extends OrgAction
{
    use WithReceivables;

    public const array HORIZONS = [30, 60, 90];

    public const int DAYS_TO_INVOICE = 7;

    public const array PIPELINE_STATES = [
        OrderStateEnum::SUBMITTED,
        OrderStateEnum::IN_WAREHOUSE,
        OrderStateEnum::HANDLING,
        OrderStateEnum::HANDLING_BLOCKED,
        OrderStateEnum::PICKED,
        OrderStateEnum::PACKING,
        OrderStateEnum::PACKED,
    ];

    /**
     * @param Collection<int, Organisation> $organisations
     * @return array<string, mixed>
     */
    public function handle(Organisation|Group $parent, Collection $organisations, User $user, ?string $organisationSlug = null, ?Carbon $today = null): array
    {
        $today ??= now();
        $isGroup = $parent instanceof Group;

        $cash        = ShowCash::make()->handle($parent, $organisations);
        $incoming    = ShowBankIncomingDashboard::make()->handle($parent, '90d', $isGroup ? IndexBankIncoming::make()->forGroup($parent, $user, $organisationSlug) : IndexBankIncoming::make());
        $outgoing    = ShowBankOutgoingDashboard::make()->handle($parent, '90d', $isGroup ? IndexBankOutgoing::make()->forGroup($parent, $user, $organisationSlug) : IndexBankOutgoing::make());
        $receivables = ShowReceivablesDashboard::make()->handle($parent, $organisations, $today);
        $payouts     = ShowPayouts::make()->handle($parent, $organisations);
        $payables    = GetPayables::query($organisations->pluck('id')->all(), $parent->currency_id)->where('counted', true)->get();
        $sum         = fn (Collection $rows) => round((float) $rows->sum(fn (object $row) => (float) $row->amount), 2);

        $needsPerson = fn (array $dashboard) => (int) (collect($dashboard['sections'])->firstWhere('key', 'needs_person')['lines'] ?? 0);

        return [
            'cash'        => [
                'amount'        => $cash['total']['amount'],
                'as_of'         => $cash['total']['as_of'],
                'stale_amount'  => $cash['total']['stale_amount'],
                'not_connected' => $cash['not_connected'],
            ],
            'flows'       => [
                'in'  => $incoming['total'],
                'out' => $outgoing['total'],
            ],
            'needs'       => [
                'bank_incoming' => $needsPerson($incoming),
                'bank_outgoing' => $needsPerson($outgoing),
                'payouts'       => $payouts['totals']['not_reconciled']['count'],
                'stale_banks'   => collect($cash['connections'])->where('status', '!=', 'ok')->count(),
            ],
            'receivables' => [
                'amount'       => $receivables['total']['amount'],
                'overdue'      => $receivables['total']['overdue'],
                'debtors'      => $receivables['total']['debtors'],
                'ageing'       => $receivables['ageing'],
                'marketplaces' => $receivables['marketplaces']['amount'],
                'legacy'       => $receivables['legacy']['amount'],
            ],
            'payables'    => [
                'amount'      => $sum($payables->whereNotNull('amount')),
                'count'       => $payables->count(),
                'unconverted' => $payables->whereNull('amount')->count(),
            ],
            'held'        => [
                'amount'   => $payouts['totals']['held'],
                'not_seen' => $payouts['totals']['not_seen'],
                'by'       => collect($payouts['rows'])->where('held.status', 'known')->groupBy('label')
                    ->map(fn (Collection $rows, string $label) => ['key' => $label, 'label' => $label, 'amount' => round((float) $rows->sum('held.amount'), 2)])
                    ->sortByDesc('amount')->values()->all(),
            ],
            'forecast'    => $this->forecast($parent, $organisations, $today, $cash['total']['amount'], $payouts, $sum($payables->whereNotNull('amount'))),
        ];
    }

    /**
     * @param Collection<int, Organisation> $organisations
     * @param array<string, mixed> $payouts
     * @return array<string, mixed>
     */
    private function forecast(Organisation|Group $parent, Collection $organisations, Carbon $today, float $cash, array $payouts, float $payables): array
    {
        $days      = '(-'.self::daysOverdueSql($today).')';
        $converted = self::convertedSql($parent);
        $exchange  = $parent instanceof Group ? 'grp_exchange' : 'org_exchange';
        $owed      = self::owedInvoices(DB::table('invoices'), $parent, $organisations)
            ->whereNull('invoices.source_id')
            ->whereNull('invoices.as_organisation_id')
            ->selectRaw("$days as days, sum($converted) as amount")
            ->groupByRaw('1')
            ->get();

        $pipeline = DB::table('orders')
            ->join('customers', 'customers.id', 'orders.customer_id')
            ->whereIn('orders.organisation_id', $organisations->pluck('id'))
            ->whereIn('orders.state', array_map(fn (OrderStateEnum $state) => $state->value, self::PIPELINE_STATES))
            ->whereNull('orders.deleted_at')
            ->whereRaw('orders.total_amount - orders.payment_amount > 0')
            ->selectRaw(self::DAYS_TO_INVOICE.' + coalesce(customers.payment_terms_days, 0) as days, sum((orders.total_amount - orders.payment_amount) * orders.'.$exchange.') as amount, count(*) as orders')
            ->groupByRaw('1')
            ->get();

        $providers = round((float) collect($payouts['rows'])
            ->where('kind', 'provider')->where('provider', '!=', 'cod')->where('held.status', 'known')
            ->sum('held.amount'), 2);

        $within = fn (Collection $rows, int $horizon) => round((float) $rows->filter(fn (object $row) => (int) $row->days >= 0 && (int) $row->days <= $horizon)->sum('amount'), 2);

        return [
            'start'          => $cash,
            'overdue'        => round((float) $owed->filter(fn (object $row) => (int) $row->days < 0)->sum('amount'), 2),
            'pipeline_later' => round((float) $pipeline->filter(fn (object $row) => (int) $row->days > max(self::HORIZONS))->sum('amount'), 2),
            'pipeline_orders' => (int) $pipeline->sum('orders'),
            'horizons'       => collect(self::HORIZONS)->map(function (int $horizon) use ($cash, $owed, $pipeline, $providers, $payables, $within) {
                $receivables = $within($owed, $horizon);
                $orders      = $within($pipeline, $horizon);

                return [
                    'days'        => $horizon,
                    'receivables' => $receivables,
                    'providers'   => $providers,
                    'pipeline'    => $orders,
                    'payables'    => $payables,
                    'balance'     => round($cash + $receivables + $providers + $orders - $payables, 2),
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inGroup(ActionRequest $request): array
    {
        $this->readGroup($request);

        return $this->handle($this->group, $this->readOrganisations, $request->user(), $this->organisationSlug);
    }

    public function htmlResponse(array $dashboard, ActionRequest $request): Response
    {
        $query = array_filter(['org' => $this->organisationSlug]);

        return Inertia::render(
            'Org/Accounting/FinancialsDashboard',
            [
                'breadcrumbs'   => array_merge(
                    ShowGroupDashboard::make()->getBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']]]
                ),
                'title'         => __('Financials'),
                'pageHead'      => [
                    'title' => __('Financials'),
                    'icon'  => ['fal', 'fa-file-invoice-dollar'],
                ],
                'organisation'  => $this->organisationSlug,
                'organisations' => $this->organisationOptions(),
                'currency'      => $this->group->currency->code,
                'routes'        => collect([
                    'cash'          => 'grp.financials.cash.index',
                    'bank_incoming' => 'grp.financials.bank_incoming.index',
                    'bank_outgoing' => 'grp.financials.bank_outgoing.index',
                    'needs_in'      => 'grp.financials.bank_incoming.needs_person',
                    'needs_out'     => 'grp.financials.bank_outgoing.needs_person',
                    'payouts'       => 'grp.financials.payouts.index',
                    'receivables'   => 'grp.financials.receivables.index',
                    'payables'      => 'grp.financials.payables.index',
                ])->map(fn (string $name) => ['name' => $name, 'parameters' => $query])->all(),
                'dashboard'     => $dashboard,
            ]
        );
    }
}
