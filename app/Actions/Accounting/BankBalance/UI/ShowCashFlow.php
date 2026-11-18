<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankBalance\UI;

use App\Actions\Accounting\BankPayee\ClassifyBankOutgoing;
use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\Accounting\BankTransaction\UI\IndexBankOutgoing;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The cash flow of one organisation's connected banks, week by week (Monday to Sunday) or month by month: the cash
 * at the start, the money received and paid by Bank incoming and Bank outgoing section, and the cash at the end.
 * The group view adds up the organisations the user may see, each converted to the group's currency at the same
 * rates, period by period. Weeks and months follow each organisation's time zone.
 *
 * The cash at any moment is worked back from the last balances read from each bank, less the lines booked after them:
 * a currency missing from that reading holds nothing (the bank closed it), and a bank never read is taken as starting
 * from nothing before its first line. Balances are converted at the rate of the day they are read, lines at the rate of the day they were
 * booked. Moves between our own balances (currency conversions) are not money in or out: they and the change of the
 * exchange rates are the difference left between the cash at the start and at the end.
 */
class ShowCashFlow extends OrgAction
{
    use WithAccountingPagesAuthorisation {
        authorize as authorizeInOrganisation;
    }

    public const array INTERVALS = ['week', 'month'];

    public const int PERIODS = 12;

    /** @var Collection<int, Organisation>|null */
    private ?Collection $groupOrganisations = null;

    private ?string $organisationSlug = null;

    /**
     * @param Collection<int, Organisation>|null $organisations the organisations read when the parent is the group
     * @return array<string, mixed>
     */
    public function handle(Organisation|Group $parent, string $interval = 'week', ?Carbon $now = null, ?Collection $organisations = null): array
    {
        if ($parent instanceof Organisation) {
            return $this->ofOrganisation($parent, $interval, $now, (int) $parent->currency_id);
        }

        $target = (int) $parent->currency_id;
        $each   = ($organisations ?? collect())->map(fn (Organisation $organisation) => [$organisation, $this->ofOrganisation($organisation, $interval, $now, $target)]);
        $sum    = fn (Collection $sections) => collect($sections->first())->map(fn (float $amount, string $key) => round($sections->sum($key), 2))->all();

        $periods = $each->isEmpty() ? collect() : collect(range(0, self::PERIODS - 1))->map(function (int $index) use ($each, $sum) {
            $rows = $each->map(fn (array $pair) => $pair[1]['periods'][$index]);

            return array_merge($rows->first(), [
                'opening'     => round($rows->sum('opening'), 2),
                'in'          => $sum($rows->pluck('in')),
                'total_in'    => round($rows->sum('total_in'), 2),
                'out'         => $sum($rows->pluck('out')),
                'total_out'   => round($rows->sum('total_out'), 2),
                'conversions' => round($rows->sum('conversions'), 2),
                'closing'     => round($rows->sum('closing'), 2),
            ]);
        });
        $withBanks = $each->filter(fn (array $pair) => count($pair[1]['banks']) > 0);

        return [
            'currency'       => $parent->currency->code,
            'interval'       => $interval,
            'periods'        => $periods->all(),
            'banks'          => $each->flatMap(fn (array $pair) => collect($pair[1]['banks'])->map(fn (array $bank) => array_merge($bank, ['name' => $bank['name'].' ('.$pair[0]->code.')'])))->values()->all(),
            'timezone'       => $each->pluck('1.timezone')->unique()->implode(', '),
            'checked_from'   => $withBanks->contains(fn (array $pair) => $pair[1]['checked_from'] === null) ? null : $withBanks->pluck('1.checked_from')->max(),
            'unconverted'    => $each->flatMap(fn (array $pair) => $pair[1]['unconverted'])->unique()->sort()->values()->all(),
            'not_connected'  => ShowCash::notConnected($each->pluck(0)),
            'organisations'  => $withBanks->map(fn (array $pair) => [
                'code'    => $pair[0]->code,
                'name'    => $pair[0]->name,
                'closing' => collect($pair[1]['periods'])->last()['closing'],
                'net'     => round(collect($pair[1]['periods'])->sum(fn (array $period) => $period['total_in'] - $period['total_out']), 2),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ofOrganisation(Organisation $organisation, string $interval, ?Carbon $now, int $target): array
    {
        $timezone = $organisation->timezone?->name ?? 'UTC';
        $now      = ($now ?? now())->copy()->setTimezone($timezone);
        $current  = $interval === 'month' ? $now->copy()->startOfMonth() : $now->copy()->startOfWeek(Carbon::MONDAY);
        $starts   = collect(range(self::PERIODS - 1, 0))->map(fn (int $back) => $interval === 'month' ? $current->copy()->subMonthsNoOverflow($back) : $current->copy()->subWeeks($back));
        $bounds   = $starts->map(fn (Carbon $start) => $start->copy())->push($interval === 'month' ? $current->copy()->addMonthNoOverflow() : $current->copy()->addWeek())
            ->map(fn (Carbon $bound) => $bound->min($now));
        $from     = $starts->first();
        $rates    = $this->rates($target, $from->copy()->subDays(30), $now);
        $convert  = fn (float $amount, int $currencyId, Carbon $day) => $currencyId === $target ? $amount : (($rate = $rates($currencyId, $day)) === null ? null : $amount * $rate);

        $lines = DB::table('bank_transactions')
            ->where('organisation_id', $organisation->id)
            ->where('status', BankTransactionStatusEnum::BOOKED);

        $heldNow = (clone $lines)
            ->selectRaw('bank_connection_id, currency_id, sum(amount) as amount, min(booked_at) as first_line')
            ->groupBy('bank_connection_id', 'currency_id')
            ->get()
            ->keyBy(fn (object $row) => $row->bank_connection_id.'-'.$row->currency_id);
        $snapshots = DB::table('bank_balances')
            ->where('organisation_id', $organisation->id)
            ->whereRaw('bank_balances.date = (select max(latest.date) from bank_balances latest where latest.bank_connection_id = bank_balances.bank_connection_id)')
            ->get(['bank_connection_id', 'currency_id', 'amount', 'taken_at'])
            ->keyBy(fn (object $row) => $row->bank_connection_id.'-'.$row->currency_id);
        $readAt = $snapshots->groupBy('bank_connection_id')->map(fn (Collection $rows) => $rows->max('taken_at'));
        foreach ($heldNow->keys()->merge($snapshots->keys())->unique() as $key) {
            $bankConnectionId = (int) explode('-', $key)[0];
            if (!$readAt->has($bankConnectionId)) {
                continue;
            }
            $snapshot = $snapshots->get($key);
            $takenAt  = $snapshot->taken_at ?? $readAt[$bankConnectionId];
            $after    = (float) (clone $lines)->where('bank_connection_id', $bankConnectionId)->where('currency_id', (int) explode('-', $key)[1])->where('booked_at', '>', $takenAt)->sum('amount');
            $heldNow->put($key, (object) [
                'bank_connection_id' => $bankConnectionId,
                'currency_id'        => (int) explode('-', $key)[1],
                'amount'             => (float) ($snapshot->amount ?? 0) + $after,
                'first_line'         => $heldNow->get($key)->first_line ?? null,
            ]);
        }

        $unconverted = [];
        $cashAt      = function (Carbon $bound) use ($lines, $heldNow, $convert, &$unconverted) {
            $before = (clone $lines)->where('booked_at', '>=', $bound->copy()->utc())->selectRaw('bank_connection_id, currency_id, sum(amount) as amount')->groupBy('bank_connection_id', 'currency_id')->get()
                ->keyBy(fn (object $row) => $row->bank_connection_id.'-'.$row->currency_id);

            return round($heldNow->sum(function (object $row, string $key) use ($before, $bound, $convert, &$unconverted) {
                $native    = (float) $row->amount - (float) ($before->get($key)->amount ?? 0);
                $converted = $convert($native, (int) $row->currency_id, $bound);
                if ($converted === null && abs($native) >= 0.005) {
                    $unconverted[(int) $row->currency_id] = true;
                }

                return $converted ?? 0;
            }), 2);
        };

        $flows = (clone $lines)
            ->where('booked_at', '>=', $from->copy()->utc())
            ->where(fn ($query) => $query->whereNull('provider_type')->orWhere('provider_type', '!=', ClassifyBankOutgoing::OWN_BALANCE_TYPE))
            ->selectRaw("date_trunc('$interval', booked_at at time zone ?)::date as period, direction, (booked_at at time zone ?)::date as day, currency_id", [$timezone, $timezone])
            ->selectRaw("case when direction = 'in' then (".IndexBankIncoming::BUCKET_SQL.') else ('.IndexBankOutgoing::BUCKET_SQL.') end as section')
            ->selectRaw('sum(amount) as amount')
            ->groupByRaw('1, 2, 3, 4, 5')
            ->get()
            ->groupBy('period');

        $periods = $starts->map(function (Carbon $start, int $index) use ($bounds, $flows, $convert, $cashAt, &$unconverted) {
            $in  = array_fill_keys(IndexBankIncoming::SECTIONS, 0.0);
            $out = array_fill_keys(IndexBankOutgoing::SECTIONS, 0.0);
            foreach ($flows->get($start->toDateString(), []) as $flow) {
                $amount = $convert((float) $flow->amount, (int) $flow->currency_id, Carbon::parse($flow->day));
                if ($amount === null) {
                    $unconverted[(int) $flow->currency_id] = true;
                    continue;
                }
                if ($flow->direction === 'in') {
                    $in[$flow->section] += $amount;
                } else {
                    $out[$flow->section] -= $amount;
                }
            }
            $in       = array_map(fn (float $amount) => round($amount, 2), $in);
            $out      = array_map(fn (float $amount) => round($amount, 2), $out);
            $opening  = $cashAt($bounds[$index]);
            $closing  = $cashAt($bounds[$index + 1]);
            $totalIn  = round(array_sum($in), 2);
            $totalOut = round(array_sum($out), 2);

            return [
                'start'       => $start->toDateString(),
                'end'         => $bounds[$index + 1]->copy()->subSecond()->toDateString(),
                'opening'     => $opening,
                'in'          => $in,
                'total_in'    => $totalIn,
                'out'         => $out,
                'total_out'   => $totalOut,
                'conversions' => round($closing - $opening - $totalIn + $totalOut, 2),
                'closing'     => $closing,
            ];
        });

        $history = BankConnection::where('organisation_id', $organisation->id)->orderBy('name')->get()->map(fn (BankConnection $bankConnection) => [
            'name'       => $bankConnection->name,
            'first_line' => $heldNow->where('bank_connection_id', $bankConnection->id)->pluck('first_line')->filter()->min(),
        ]);

        return [
            'currency'      => Currency::find($target)->code,
            'interval'      => $interval,
            'periods'       => $periods->all(),
            'banks'         => $history->map(fn (array $bank) => [
                'name'       => $bank['name'],
                'first_line' => $bank['first_line'] ? Carbon::parse($bank['first_line'])->toDateString() : null,
                'partial'    => $bank['first_line'] === null || Carbon::parse($bank['first_line'])->gt($from),
            ])->all(),
            'timezone'      => $timezone,
            'checked_from'  => DB::table('bank_balances')->where('organisation_id', $organisation->id)->min('date'),
            'unconverted'   => Currency::whereIn('id', array_keys($unconverted))->orderBy('code')->pluck('code')->all(),
            'not_connected' => ShowCash::notConnected(collect([$organisation])),
        ];
    }

    /**
     * The rate from each currency to the target on a day: the last one known on or before it, null when none is.
     *
     * @return \Closure(int, Carbon): ?float
     */
    private function rates(int $target, Carbon $from, Carbon $to): \Closure
    {
        $pivot = (int) Currency::where('code', config('app.currency_exchange.pivot'))->value('id');
        $before = DB::table('currency_exchanges')
            ->where('date', '<', $from->toDateString())
            ->selectRaw('distinct on (currency_id) currency_id, date, exchange')
            ->orderBy('currency_id')->orderByDesc('date')
            ->get();
        /** @var Collection<int, Collection<int, object>> $known */
        $known = $before->concat(
            DB::table('currency_exchanges')->whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderBy('date')->get(['currency_id', 'date', 'exchange'])
        )->groupBy('currency_id');
        $toPivot = function (int $currencyId, Carbon $day) use ($known, $pivot): ?float {
            if ($currencyId === $pivot) {
                return 1.0;
            }
            $rate = $known->get($currencyId)?->filter(fn (object $row) => $row->date <= $day->toDateString())->last()?->exchange;

            return $rate ? (float) $rate : null;
        };

        return function (int $currencyId, Carbon $day) use ($toPivot, $target): ?float {
            $targetRate = $toPivot($target, $day);
            $ownRate    = $toPivot($currencyId, $day);

            return $targetRate === null || !$ownRate ? null : $targetRate / $ownRate;
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->interval($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function inGroup(ActionRequest $request): array
    {
        $this->groupOrganisations = IndexBankIncoming::accountingOrganisations(group(), $request->user());
        $this->initialisationFromGroup(group(), $request);
        $picked                 = is_string($request->query('org')) ? $this->groupOrganisations->firstWhere('slug', $request->query('org')) : null;
        $this->organisationSlug = $picked?->slug;

        return $this->handle($this->group, $this->interval($request), null, $picked ? collect([$picked]) : $this->groupOrganisations);
    }

    private function interval(ActionRequest $request): string
    {
        return in_array($request->query('interval'), self::INTERVALS, true) ? $request->query('interval') : 'week';
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $this->groupOrganisations === null) {
            return $this->authorizeInOrganisation($request);
        }

        return $this->groupOrganisations->isNotEmpty();
    }

    public function htmlResponse(array $cashFlow, ActionRequest $request): Response
    {
        $isGroup = $this->groupOrganisations !== null;

        return Inertia::render(
            'Org/Accounting/CashFlow',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($isGroup, $request->route()->originalParameters()),
                'title'        => __('Cash flow'),
                'pageHead'     => [
                    'title' => __('Cash flow'),
                    'icon'  => ['fal', 'fa-exchange-alt'],
                ],
                'organisation'  => $isGroup ? $this->organisationSlug : $this->organisation->slug,
                'organisations' => $isGroup ? $this->groupOrganisations->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->all() : [],
                'isGroup'       => $isGroup,
                'inSections'   => collect(IndexBankIncoming::SECTIONS)->mapWithKeys(fn (string $section) => [$section => $this->incomingLabel($section)])->all(),
                'outSections'  => collect(IndexBankOutgoing::sections())->map(fn (array $section) => $section[0])->all(),
                'cashFlow'     => $cashFlow,
            ]
        );
    }

    private function incomingLabel(string $section): string
    {
        return match ($section) {
            'customers'        => __('Customers'),
            'marketplaces'     => __('Marketplaces'),
            'payment_accounts' => __('Payment providers'),
            'other'            => __('Other'),
            default            => __('Needs a person'),
        };
    }

    public function getBreadcrumbs(bool $isGroup, array $routeParameters): array
    {
        if ($isGroup) {
            return array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']],
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.cash_flow.index'], 'label' => __('Cash flow')]],
                ]
            );
        }

        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.org.accounting.cash_flow.index', 'parameters' => $routeParameters], 'label' => __('Cash flow')]],
            ]
        );
    }
}
