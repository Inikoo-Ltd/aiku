<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankBalance\UI;

use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Accounting\BankConnection\BankConnectionStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankBalance;
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
 * The cash the connected banks hold, from the balance snapshots kept at each bank sync: the total in the
 * organisation's currency (the group's when the whole group is read), what each connection holds in each currency,
 * and how the total moved. A day without a snapshot carries the last one before it; each amount is converted at
 * the last rate known on the day of its snapshot, and left out of the totals when no rate is known. A connection never
 * read counts the lines booked on it, at today's rate, as the cash flow does, and is flagged as not current. A connection
 * whose last snapshot is older than STALE_HOURS, or whose last reading failed, is flagged, never shown as current.
 */
class ShowCash extends OrgAction
{
    use WithAccountingPagesAuthorisation {
        authorize as authorizeInOrganisation;
    }

    public const int STALE_HOURS = 6;

    public const int TREND_DAYS = 365;

    /** The banks each organisation uses that are not connected to aiku yet, by organisation slug. */
    public const array NOT_CONNECTED = [
        'sk' => ['Tatra banka'],
        'es' => ['Santander', 'Sabadell'],
        'aw' => ['HSBC UK'],
    ];

    /**
     * @param Collection<int, Organisation> $organisations
     * @return array<int, string>
     */
    public static function notConnected(Collection $organisations): array
    {
        return $organisations->flatMap(fn (Organisation $organisation) => self::NOT_CONNECTED[$organisation->slug] ?? [])->unique()->values()->all();
    }

    /** @var Collection<int, Organisation>|null */
    private ?Collection $groupOrganisations = null;

    private ?string $organisationSlug = null;

    /**
     * @param Collection<int, Organisation>|null $organisations the organisations read when the parent is the group
     * @return array<string, mixed>
     */
    public function handle(Organisation|Group $parent, ?Collection $organisations = null): array
    {
        $organisations ??= $parent instanceof Organisation ? collect([$parent]) : collect();
        $target          = (int) $parent->currency_id;
        $pivot           = (int) Currency::where('code', config('app.currency_exchange.pivot'))->value('id');
        $rate            = fn (string $currencyId, string $date = 'bank_balances.date') => "case when $currencyId = $pivot then 1 else (select ce.exchange from currency_exchanges ce where ce.currency_id = $currencyId and ce.date <= $date order by ce.date desc limit 1) end";
        $today           = now()->toDateString();

        $snapshots = BankBalance::query()
            ->whereIn('bank_balances.organisation_id', $organisations->pluck('id'))
            ->where('bank_balances.date', '>=', now()->subDays(self::TREND_DAYS)->toDateString())
            ->join('currencies', 'currencies.id', 'bank_balances.currency_id')
            ->select(['bank_balances.bank_connection_id', 'currencies.code', 'bank_balances.date', 'bank_balances.amount', 'bank_balances.reserved_amount', 'bank_balances.taken_at'])
            ->selectRaw("case when bank_balances.currency_id = $target then 1 else ({$rate((string) $target)}) / nullif(({$rate('bank_balances.currency_id')}), 0) end as rate")
            ->orderBy('bank_balances.date')
            ->orderBy('currencies.code')
            ->toBase()
            ->get()
            ->groupBy('bank_connection_id')
            ->map(fn (Collection $rows) => $rows->groupBy('date'));

        $neverRead = DB::table('bank_transactions')
            ->whereIn('bank_transactions.organisation_id', $organisations->pluck('id'))
            ->where('bank_transactions.status', BankTransactionStatusEnum::BOOKED)
            ->whereNotExists(fn ($query) => $query->from('bank_balances')->whereColumn('bank_balances.bank_connection_id', 'bank_transactions.bank_connection_id'))
            ->join('currencies', 'currencies.id', 'bank_transactions.currency_id')
            ->groupBy('bank_transactions.bank_connection_id', 'bank_transactions.currency_id', 'currencies.code')
            ->select(['bank_transactions.bank_connection_id', 'currencies.code'])
            ->selectRaw('sum(bank_transactions.amount) as amount, 0 as reserved_amount')
            ->selectRaw("case when bank_transactions.currency_id = $target then 1 else ({$rate((string) $target, "'$today'")}) / nullif(({$rate('bank_transactions.currency_id', "'$today'")}), 0) end as rate")
            ->get()
            ->groupBy('bank_connection_id');

        $converted = fn (object $row, string $field) => $row->rate === null ? null : round((float) $row->{$field} * (float) $row->rate, 2);

        $connections = BankConnection::whereIn('organisation_id', $organisations->pluck('id'))
            ->with('organisation:id,code,name,slug')
            ->orderBy('name')
            ->get()
            ->map(function (BankConnection $bankConnection) use ($snapshots, $neverRead, $converted) {
                $latest   = $snapshots->get($bankConnection->id)?->last() ?? $neverRead->get($bankConnection->id) ?? collect();
                $takenAt  = $latest->max('taken_at') ? Carbon::parse($latest->max('taken_at')) : null;
                $failedAt = $bankConnection->state === BankConnectionStateEnum::ERROR
                    ? $bankConnection->last_error_at?->toIso8601String()
                    : ($bankConnection->sync_data['balances_error_at'] ?? null);
                $balances = $latest->map(fn (object $row) => [
                    'currency'           => $row->code,
                    'amount'             => (float) $row->amount,
                    'reserved'           => (float) $row->reserved_amount,
                    'converted'          => $converted($row, 'amount'),
                    'converted_reserved' => $converted($row, 'reserved_amount'),
                ])->sortByDesc(fn (array $balance) => $balance['converted'] ?? 0)->values();

                return [
                    'id'                => $bankConnection->id,
                    'name'              => $bankConnection->name,
                    'organisation'      => $bankConnection->organisation->name,
                    'organisation_code' => $bankConnection->organisation->code,
                    'status'            => match (true) {
                        $bankConnection->state === BankConnectionStateEnum::ERROR || isset($bankConnection->sync_data['balances_error']) => 'failed',
                        $takenAt === null                                    => 'never',
                        $takenAt->lt(now()->subHours(self::STALE_HOURS))     => 'stale',
                        default                                              => 'ok',
                    },
                    'taken_at'          => $takenAt?->toIso8601String(),
                    'from_lines'        => $takenAt === null && $latest->isNotEmpty(),
                    'failed_at'         => $failedAt,
                    'amount'            => round($balances->sum('converted'), 2),
                    'reserved'          => round($balances->sum('converted_reserved'), 2),
                    'balances'          => $balances->all(),
                ];
            });

        $trend = [];
        $first = $snapshots->map(fn (Collection $days) => $days->keys()->first())->min();
        if ($first) {
            $held = [];
            for ($day = Carbon::parse($first); $day->toDateString() <= $today; $day->addDay()) {
                foreach ($snapshots as $bankConnectionId => $days) {
                    if ($days->has($day->toDateString())) {
                        $held[$bankConnectionId] = $days[$day->toDateString()]->sum(fn (object $row) => $converted($row, 'amount') ?? 0);
                    }
                }
                $trend[$day->toDateString()] = round(array_sum($held), 2);
            }
        }
        if ($neverRead->isNotEmpty()) {
            $trend[$today] = round(($trend[$today] ?? 0) + $neverRead->flatten(1)->sum(fn (object $row) => $converted($row, 'amount') ?? 0), 2);
        }

        $balances = $connections->flatMap(fn (array $connection) => $connection['balances']);
        $ranked   = fn (Collection $rows, string $key) => $rows->groupBy($key)
            ->map(fn (Collection $group, string $label) => ['key' => $label, 'label' => $label, 'amount' => round($group->sum(fn (array $row) => $row['converted'] ?? $row['amount']), 2)])
            ->sortByDesc('amount')->values()->all();

        return [
            'total'         => [
                'amount'       => round($connections->sum('amount'), 2),
                'reserved'     => round($connections->sum('reserved'), 2),
                'as_of'        => $connections->pluck('taken_at')->filter()->min(),
                'stale_amount' => round($connections->where('status', '!=', 'ok')->sum('amount'), 2),
                'ago_7'        => $trend[now()->subDays(7)->toDateString()] ?? null,
                'ago_30'       => $trend[now()->subDays(30)->toDateString()] ?? null,
            ],
            'connections'   => $connections->all(),
            'organisations' => $parent instanceof Group ? $ranked($connections->map(fn (array $connection) => ['organisation' => $connection['organisation'], 'converted' => $connection['amount']]), 'organisation') : [],
            'currencies'    => $balances->whereNotNull('converted')->groupBy('currency')
                ->map(fn (Collection $group, string $code) => [
                    'key'      => $code,
                    'label'    => $code,
                    'amount'   => round($group->sum('converted'), 2),
                    'native'   => round($group->sum('amount'), 2),
                    'reserved' => round($group->sum('reserved'), 2),
                ])
                ->sortByDesc('amount')->values()->all(),
            'unconverted'   => $balances->whereNull('converted')->groupBy('currency')
                ->map(fn (Collection $group, string $code) => ['currency' => $code, 'amount' => round($group->sum('amount'), 2)])
                ->values()->all(),
            'trend'         => ['dates' => array_keys($trend), 'totals' => array_values($trend)],
            'not_connected' => self::notConnected($organisations),
            'stale_hours'   => self::STALE_HOURS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
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

        return $this->handle($this->group, $picked ? collect([$picked]) : $this->groupOrganisations);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $this->groupOrganisations === null) {
            return $this->authorizeInOrganisation($request);
        }

        return $this->groupOrganisations->isNotEmpty();
    }

    public function htmlResponse(array $cash, ActionRequest $request): Response
    {
        $isGroup = $this->groupOrganisations !== null;
        $parent  = $isGroup ? $this->group : $this->organisation;

        return Inertia::render(
            'Org/Accounting/Cash',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($isGroup, $request->route()->originalParameters()),
                'title'         => __('Cash'),
                'pageHead'      => [
                    'title' => __('Cash'),
                    'icon'  => ['fal', 'fa-coins'],
                ],
                'organisation'  => $this->organisationSlug,
                'organisations' => $isGroup ? $this->groupOrganisations->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->all() : [],
                'isGroup'       => $isGroup,
                'currency'      => $parent->currency->code,
                'cash'          => $cash,
            ]
        );
    }

    public function getBreadcrumbs(bool $isGroup, array $routeParameters): array
    {
        if ($isGroup) {
            return array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']],
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.cash.index'], 'label' => __('Cash')]],
                ]
            );
        }

        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.org.accounting.cash.index', 'parameters' => $routeParameters], 'label' => __('Cash')]],
            ]
        );
    }
}
