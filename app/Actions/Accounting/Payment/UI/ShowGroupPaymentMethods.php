<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Which payment methods customers pay with and how each one grows, for the organisations of the group the user may
 * see: completed payments (not refunds, not bank fees), by month in Bratislava time, the current month to date.
 * A payment without a method (all of them before 10 Feb 2026) takes it from its payment account; a card payment
 * takes its brand from sub_method, "brand not recorded" when there is none.
 * Online is what a card processor takes: cards, Apple Pay, Google Pay, Klarna and the local methods.
 * What customers paid from their balance is credit already held: it is shown, never counted in totals or shares.
 * Organisations are in their own currency, the group in the group currency.
 * Run rates are the last three complete months a year.
 */
class ShowGroupPaymentMethods extends OrgAction
{
    public const string TIMEZONE = 'Europe/Bratislava';

    public const array PERIODS = [3, 6, 12, 24];

    public const array CARD_RAW = ['card', 'checkout', 'mastercard'];

    public const array LOCAL_METHODS = ['ideal', 'p24', 'bancontact', 'blik', 'eps', 'swish'];

    public const array NEW_METHODS = ['klarna', 'ideal', 'p24', 'bancontact', 'blik', 'eps', 'swish'];

    /**
     * Each method as a row of the table, online first, with the band of the chart it belongs to.
     *
     * @var array<string, array{label: string, group: string, online: bool}>
     */
    public const array METHODS = [
        'mastercard'       => ['label' => 'Mastercard', 'group' => 'cards', 'online' => true],
        'visa'             => ['label' => 'Visa', 'group' => 'cards', 'online' => true],
        'amex'             => ['label' => 'Amex', 'group' => 'cards', 'online' => true],
        'card'             => ['label' => 'Card (other)', 'group' => 'cards', 'online' => true],
        'applepay'         => ['label' => 'Apple Pay', 'group' => 'wallets', 'online' => true],
        'googlepay'        => ['label' => 'Google Pay', 'group' => 'wallets', 'online' => true],
        'klarna'           => ['label' => 'Klarna', 'group' => 'klarna', 'online' => true],
        'ideal'            => ['label' => 'iDEAL', 'group' => 'local', 'online' => true],
        'p24'              => ['label' => 'Przelewy24', 'group' => 'local', 'online' => true],
        'bancontact'       => ['label' => 'Bancontact', 'group' => 'local', 'online' => true],
        'blik'             => ['label' => 'BLIK', 'group' => 'local', 'online' => true],
        'eps'              => ['label' => 'EPS', 'group' => 'local', 'online' => true],
        'swish'            => ['label' => 'Swish', 'group' => 'local', 'online' => true],
        'bank'             => ['label' => 'Bank', 'group' => 'bank', 'online' => false],
        'cash_on_delivery' => ['label' => 'COD', 'group' => 'cash_on_delivery', 'online' => false],
        'paypal'           => ['label' => 'PayPal', 'group' => 'paypal', 'online' => false],
        'pastpay'          => ['label' => 'PastPay', 'group' => 'other', 'online' => false],
        'cash'             => ['label' => 'Cash', 'group' => 'other', 'online' => false],
        'other'            => ['label' => 'Other', 'group' => 'other', 'online' => false],
    ];

    /**
     * The methods as customers see them, in the order the monthly share chart stacks them: the new ones at the bottom.
     *
     * @var array<string, array{label: string, colour: string}>
     */
    public const array GROUPS = [
        'klarna'           => ['label' => 'Klarna', 'colour' => '#ee7fac'],
        'local'            => ['label' => 'Local payments', 'colour' => '#f2a600'],
        'cards'            => ['label' => 'Cards', 'colour' => '#008a17'],
        'wallets'          => ['label' => 'Apple Pay / Google Pay', 'colour' => '#00b894'],
        'paypal'           => ['label' => 'PayPal', 'colour' => '#f47230'],
        'bank'             => ['label' => 'Bank', 'colour' => '#6a4ce2'],
        'cash_on_delivery' => ['label' => 'COD', 'colour' => '#ec4647'],
        'other'            => ['label' => 'PastPay, cash and other', 'colour' => '#9ca3af'],
    ];

    public const string BALANCE = 'account';

    public const array EMERGING = ['wallets', 'local', 'klarna', 'other'];

    public const array BRANDS = ['visa' => 'Visa', 'mastercard' => 'Mastercard', 'amex' => 'Amex'];

    /** @var Collection<int, Organisation>|null */
    private ?Collection $groupOrganisations = null;

    /** @var array<string, mixed> */
    private array $filters = [];

    /**
     * @param Collection<int, Organisation> $organisations
     * @param array{organisation?: string|null, shop?: string|null, shop_type?: string|null, brand?: string|null, currency?: string|null, months?: int, intercompany?: bool} $filters
     * @return array<string, mixed>
     */
    public function handle(Group $group, Collection $organisations, array $filters = [], ?Carbon $now = null): array
    {
        $now          = ($now ?? now())->copy()->setTimezone(self::TIMEZONE);
        $months       = in_array($filters['months'] ?? 12, self::PERIODS, true) ? $filters['months'] ?? 12 : 12;
        $organisation = isset($filters['organisation']) ? $organisations->firstWhere('slug', $filters['organisation']) : null;
        $scope        = $organisation ? collect([$organisation]) : $organisations;
        $scopeIds     = $scope->pluck('id')->all();
        $from         = $now->copy()->startOfMonth()->subMonths($months - 1);
        $sameDaysCut  = $now->copy()->startOfMonth()->subMonth()->add($now->copy()->startOfMonth()->diff($now));
        $monthKeys    = array_map(fn (int $index) => $from->copy()->addMonths($index)->format('Y-m'), range(0, $months - 1));
        $weekKeys     = [];
        for ($week = $from->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY); $week <= $now; $week->addWeek()) {
            $weekKeys[] = $week->format('Y-m-d');
        }
        $complete     = array_slice($monthKeys, 0, -1);
        $lastThree    = array_slice($complete, -3);
        $threeBefore  = array_slice($complete, -6, 3);
        $lastComplete = array_slice($complete, -1);
        $current      = end($monthKeys);
        $previous     = $monthKeys[count($monthKeys) - 2] ?? null;

        $cube         = [];
        $shopCube     = [];
        $currencyList = [];
        $brand        = "case when lower(payments.sub_method) in ('american express', 'amex') then 'amex' else lower(payments.sub_method) end";
        $rows         = $this->payments($scope, $filters)
            ->join('currencies', 'currencies.id', 'payments.currency_id')
            ->where('payments.date', '>=', $from)
            ->selectRaw("payments.organisation_id, payments.shop_id, currencies.code as currency, to_char(payments.date at time zone '".self::TIMEZONE."', 'YYYY-MM') as month, to_char(date_trunc('week', payments.date at time zone '".self::TIMEZONE."'), 'YYYY-MM-DD') as week, ".self::rawMethodSql().' as raw')
            ->selectRaw('case when '.self::rawMethodSql()." in ('".implode("', '", self::CARD_RAW)."') then $brand end as brand")
            ->selectRaw('count(*) as payments, sum(payments.org_amount) as org_amount, sum(payments.grp_amount) as grp_amount')
            ->selectRaw('sum(payments.org_amount) filter (where payments.date < ?) as same_days_org, sum(payments.grp_amount) filter (where payments.date < ?) as same_days_grp', [$sameDaysCut, $sameDaysCut])
            ->groupByRaw('1, 2, 3, 4, 5, 6, 7')
            ->get();
        $weekly = [];
        foreach ($rows as $row) {
            $method = self::methodKey($row->raw, $row->brand);
            if ($method === null) {
                continue;
            }
            $currencyList[$row->currency] = true;
            $cell                         = $cube[$row->organisation_id][$method][$row->month] ?? [0, 0.0, 0.0, 0.0, 0.0];
            $cube[$row->organisation_id][$method][$row->month] = [
                $cell[0] + (int) $row->payments,
                $cell[1] + (float) $row->org_amount,
                $cell[2] + (float) $row->grp_amount,
                $cell[3] + (float) $row->same_days_org,
                $cell[4] + (float) $row->same_days_grp,
            ];
            if ($method !== self::BALANCE) {
                $groupKey                                          = self::METHODS[$method]['group'];
                $week                                              = $weekly[$row->organisation_id][$groupKey][$row->week] ?? [0.0, 0.0];
                $weekly[$row->organisation_id][$groupKey][$row->week] = [$week[0] + (float) $row->org_amount, $week[1] + (float) $row->grp_amount];
                $shopMethod                         = self::shopMethod($method);
                $shop                               = $shopCube[$row->shop_id][$shopMethod] ?? ['organisation_id' => $row->organisation_id, 'payments' => 0, 'amount' => 0.0, 'amount_group' => 0.0, 'months' => []];
                $shop['payments']                  += (int) $row->payments;
                $shop['amount']                    += (float) $row->org_amount;
                $shop['amount_group'] += (float) $row->grp_amount;
                $shop['months'][$row->month]        = ($shop['months'][$row->month] ?? 0) + (float) $row->org_amount;
                $shopCube[$row->shop_id][$shopMethod] = $shop;
            }
        }

        $launches = [];
        $launchQuery = $this->payments($scope, array_merge(array_intersect_key($filters, array_flip(['shop', 'shop_type'])), ['intercompany' => true]))
            ->selectRaw('payments.shop_id, '.self::rawMethodSql().' as raw, min(payments.date) as first_payment')
            ->groupByRaw('1, 2')
            ->get();
        foreach ($launchQuery as $row) {
            $method = self::methodKey($row->raw, null);
            if ($method !== null && $method !== self::BALANCE) {
                $shopMethod                        = self::shopMethod($method);
                $launches[$row->shop_id][$shopMethod] = min($launches[$row->shop_id][$shopMethod] ?? $row->first_payment, $row->first_payment);
            }
        }

        $shops      = DB::table('shops')->whereIn('shops.organisation_id', $organisations->pluck('id'))->orderBy('shops.code')->get(['id', 'slug', 'code', 'name', 'type', 'organisation_id'])->keyBy('id');
        $currencies = DB::table('currencies')->whereIn('id', $organisations->pluck('currency_id')->push($group->currency_id))->pluck('code', 'id');
        $allMethods = array_keys(self::METHODS);
        $online     = array_keys(array_filter(self::METHODS, fn (array $method) => $method['online']));
        $inGroup    = fn (string $groupKey) => array_keys(array_filter(self::METHODS, fn (array $method) => $method['group'] === $groupKey));

        $sum = function (array $organisationIds, array $methods, ?array $months, int $field) use ($cube): float {
            $total = 0.0;
            foreach ($organisationIds as $organisationId) {
                foreach ($methods as $method) {
                    foreach ($cube[$organisationId][$method] ?? [] as $month => $cell) {
                        if ($months === null || in_array($month, $months, true)) {
                            $total += $cell[$field];
                        }
                    }
                }
            }

            return $total;
        };
        $share = function (array $organisationIds, array $methods, ?array $months, int $field) use ($sum, $allMethods): ?float {
            $total = $sum($organisationIds, $allMethods, $months, $field);

            return $total > 0 ? round($sum($organisationIds, $methods, $months, $field) / $total * 100, 1) : null;
        };
        $yearly  = fn (array $organisationIds, array $methods, array $months, int $field) => count($months) ? round($sum($organisationIds, $methods, $months, $field) / count($months) * 12, 2) : null;
        $monthly = fn (array $organisationIds, array $methods, int $field) => array_map(fn (string $month) => round($sum($organisationIds, $methods, [$month], $field), 2), $monthKeys);
        $byWeek  = fn (array $organisationIds, int $field) => collect(self::GROUPS)->map(fn (array $groupData, string $groupKey) => array_map(
            fn (string $week) => round(array_sum(array_map(fn (int $organisationId) => $weekly[$organisationId][$groupKey][$week][$field] ?? 0, $organisationIds)), 2),
            $weekKeys
        ))->all();
        $toDate  = fn (?string $date) => $date ? Carbon::parse($date)->setTimezone(self::TIMEZONE)->toDateString() : null;

        $organisationCards = $scope->map(function (Organisation $organisation) use ($sum, $share, $yearly, $monthly, $launches, $shops, $currencies, $allMethods, $online, $inGroup, $complete, $lastThree, $lastComplete, $toDate) {
            $id       = [$organisation->id];
            $launched = $shops->where('organisation_id', $organisation->id)->keys()
                ->flatMap(fn (int $shopId) => array_values(array_intersect_key($launches[$shopId] ?? [], array_flip(self::NEW_METHODS))))->min();
            $launchedMonth = $launched ? Carbon::parse($launched)->setTimezone(self::TIMEZONE)->format('Y-m') : null;
            $firstThree    = $launchedMonth ? array_slice(array_values(array_filter($complete, fn (string $month) => $month >= $launchedMonth)), 0, 3) : [];

            return [
                'slug'            => $organisation->slug,
                'code'            => $organisation->code,
                'name'            => $organisation->name,
                'currency'        => $currencies[$organisation->currency_id] ?? null,
                'total'           => round($sum($id, $allMethods, null, 1), 2),
                'payments'        => (int) $sum($id, $allMethods, null, 0),
                'online'          => round($sum($id, $online, null, 1), 2),
                'online_share'    => $share($id, $online, null, 1),
                'run_rate'        => $yearly($id, $allMethods, $lastThree, 1),
                'online_run_rate' => $yearly($id, $online, $lastThree, 1),
                'balance'         => round($sum($id, [self::BALANCE], null, 1), 2),
                'top'             => collect(array_keys(self::GROUPS))->map(fn (string $groupKey) => ['key' => $groupKey, 'amount' => round($sum($id, $inGroup($groupKey), null, 1), 2)])
                    ->where('amount', '>', 0)->sortByDesc('amount')->take(3)->values()->all(),
                'launched'        => $toDate($launched),
                'new_share'       => [
                    'before' => count($firstThree) ? ['months' => $firstThree, 'share' => $share($id, self::NEW_METHODS, $firstThree, 1)] : null,
                    'now'    => ['months' => $lastThree, 'share' => $share($id, self::NEW_METHODS, $lastThree, 1)],
                ],
                'new_run_rate'    => $yearly($id, self::NEW_METHODS, $lastComplete, 1),
                'emerging'        => collect(self::EMERGING)->mapWithKeys(fn (string $groupKey) => [$groupKey => $monthly($id, $inGroup($groupKey), 1)])->all(),
                'emerging_group'  => collect(self::EMERGING)->mapWithKeys(fn (string $groupKey) => [$groupKey => $monthly($id, $inGroup($groupKey), 2)])->all(),
            ];
        })->values();

        $inGroupCurrency = $organisation === null;
        $field           = $inGroupCurrency ? 2 : 1;
        $columns         = $scope->count() > 1 ? $scope->filter(fn (Organisation $organisation) => isset($cube[$organisation->id]))->map(fn (Organisation $organisation) => ['key' => $organisation->slug, 'id' => $organisation->id, 'label' => $organisation->code, 'currency' => $currencies[$organisation->currency_id] ?? null])->values()->all() : [];
        $growth          = fn (float $now, float $before) => $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
        $countedTotal    = $sum($scopeIds, $allMethods, null, $field);
        $cell            = function (array $organisationIds, array $methods, int $field) use ($sum) {
            $amount   = $sum($organisationIds, $methods, null, $field);
            $payments = (int) $sum($organisationIds, $methods, null, 0);

            return ['amount' => round($amount, 2), 'payments' => $payments, 'average' => $payments ? round($amount / $payments, 2) : null];
        };
        $row = function (string $key, string $label, string $kind, array $methods) use ($cell, $columns, $sum, $growth, $yearly, $scopeIds, $field, $countedTotal, $current, $previous, $lastThree, $threeBefore) {
            return [
                'key'    => $key,
                'label'  => $label,
                'kind'   => $kind,
                'group'  => self::METHODS[$key]['group'] ?? null,
                'cells'  => collect($columns)->mapWithKeys(fn (array $column) => [$column['key'] => $cell([$column['id']], $methods, 1)])->all(),
                'total'  => array_merge($cell($scopeIds, $methods, $field), [
                    'share'         => $key === self::BALANCE || $countedTotal <= 0 ? null : round($sum($scopeIds, $methods, null, $field) / $countedTotal * 100, 1),
                    'growth_month'  => $previous ? $growth($sum($scopeIds, $methods, [$current], $field), $sum($scopeIds, $methods, [$previous], $field + 2)) : null,
                    'growth_months' => $growth($sum($scopeIds, $methods, $lastThree, $field), $sum($scopeIds, $methods, $threeBefore, $field)),
                    'run_rate'      => $yearly($scopeIds, $methods, $lastThree, $field),
                ]),
            ];
        };
        $present = fn (array $methods) => array_values(array_filter($methods, fn (string $method) => $sum($scopeIds, [$method], null, 0) > 0));
        $offline = array_values(array_diff($allMethods, $online));
        $matrix  = array_merge(
            array_map(fn (string $method) => $row($method, self::METHODS[$method]['label'], 'online', [$method]), $present($online)),
            [$row('online_total', 'Online total', 'subtotal', $online)],
            array_map(fn (string $method) => $row($method, self::METHODS[$method]['label'], 'offline', [$method]), $present($offline)),
            [$row('all', 'All customer payments', 'total', $allMethods)],
            $sum($scopeIds, [self::BALANCE], null, 0) > 0 ? [$row(self::BALANCE, 'Balance', 'memo', [self::BALANCE])] : [],
        );
        $onlineShare = ['cells' => collect($columns)->mapWithKeys(fn (array $column) => [$column['key'] => $share([$column['id']], $online, null, 1)])->all(), 'total' => $share($scopeIds, $online, null, $field)];

        $organisationCodes      = $organisations->pluck('code', 'id');
        $organisationCurrencies = $organisations->mapWithKeys(fn (Organisation $organisation) => [$organisation->id => $currencies[$organisation->currency_id] ?? null]);
        $shopRows               = [];
        foreach ($shopCube as $shopId => $methods) {
            foreach ($methods as $method => $data) {
                $shop       = $shops->get($shopId);
                $shopRows[] = [
                    'shop'          => $shop?->code,
                    'shop_name'     => $shop?->name,
                    'shop_type'     => $shop?->type,
                    'organisation'  => $organisationCodes[$data['organisation_id']] ?? null,
                    'currency'      => $organisationCurrencies[$data['organisation_id']] ?? null,
                    'method'        => $method,
                    'first_payment' => $toDate($launches[$shopId][$method] ?? null),
                    'payments'      => $data['payments'],
                    'amount'        => round($data['amount'], 2),
                    'amount_group'  => round($data['amount_group'], 2),
                    'monthly'       => round(array_sum(array_intersect_key($data['months'], array_flip($lastThree))) / max(count($lastThree), 1), 2),
                ];
            }
        }
        ksort($currencyList);

        return [
            'currency'        => $inGroupCurrency ? $currencies[$group->currency_id] : $currencies[$organisation->currency_id],
            'group_currency'  => $currencies[$group->currency_id],
            'months'          => $monthKeys,
            'complete_months' => ['last_three' => $lastThree, 'three_before' => $threeBefore, 'last' => $lastComplete, 'same_days_cut' => $sameDaysCut->toDateString()],
            'methods'         => collect(self::METHODS)->map(fn (array $method, string $key) => ['key' => $key, 'label' => $method['label'], 'colour' => self::GROUPS[$method['group']]['colour'], 'online' => $method['online']])->values()
                ->merge(collect(self::GROUPS)->map(fn (array $groupData, string $key) => ['key' => $key, 'label' => $groupData['label'], 'colour' => $groupData['colour'], 'online' => null])->values())
                ->unique('key')->values()->all(),
            'series'          => collect(self::GROUPS)->map(fn (array $groupData, string $key) => ['key' => $key, 'label' => $groupData['label'], 'colour' => $groupData['colour']])->values()->all(),
            'organisations'   => $organisationCards->all(),
            'weeks'           => $weekKeys,
            'chart'           => array_merge(
                $organisation ? [] : ['all' => $byWeek($scopeIds, 1)],
                $scope->mapWithKeys(fn (Organisation $organisation) => [$organisation->slug => $byWeek([$organisation->id], 0)])->all()
            ),
            'matrix'          => ['columns' => array_map(fn (array $column) => array_diff_key($column, ['id' => true]), $columns), 'rows' => $matrix, 'online_share' => $onlineShare],
            'shops_table'     => collect($shopRows)->sortBy([['shop', 'asc'], ['amount', 'desc']])->values()->all(),
            'options'         => [
                'shops'      => $shops->whereIn('organisation_id', $scopeIds)->map(fn (object $shop) => ['value' => $shop->slug, 'label' => $shop->code.' '.$shop->name])->values()->all(),
                'shop_types' => $shops->pluck('type')->unique()->sort()->values()->all(),
                'currencies' => array_keys($currencyList),
                'brands'     => collect(self::BRANDS)->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])->values()->all(),
                'periods'    => self::PERIODS,
            ],
        ];
    }

    /**
     * @param Collection<int, Organisation> $organisations
     * @param array<string, mixed> $filters
     */
    private function payments(Collection $organisations, array $filters): \Illuminate\Database\Query\Builder
    {
        return DB::table('payments')
            ->join('payment_accounts', 'payment_accounts.id', 'payments.payment_account_id')
            ->join('shops', 'shops.id', 'payments.shop_id')
            ->whereIn('payments.organisation_id', $organisations->pluck('id'))
            ->where('payments.type', PaymentTypeEnum::PAYMENT->value)
            ->where('payments.state', PaymentStateEnum::COMPLETED->value)
            ->when($filters['shop'] ?? null, fn ($query, string $shop) => $query->where('shops.slug', $shop))
            ->when($filters['shop_type'] ?? null, fn ($query, string $type) => $query->where('shops.type', $type))
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->whereIn('payments.currency_id', DB::table('currencies')->where('code', $currency)->select('id')))
            ->when($filters['brand'] ?? null, fn ($query, string $brand) => $query
                ->whereRaw(self::rawMethodSql()." in ('".implode("', '", self::CARD_RAW)."')")
                ->whereRaw("case when lower(payments.sub_method) in ('american express', 'amex') then 'amex' else lower(payments.sub_method) end = ?", [$brand]))
            ->when(!($filters['intercompany'] ?? false), fn ($query) => $query->whereNotExists(
                fn ($query) => $query->selectRaw('1')->from('customers')->whereColumn('customers.id', 'payments.customer_id')->whereNotNull('customers.as_organisation_id')
            ));
    }

    private static function rawMethodSql(): string
    {
        return "coalesce(nullif(payments.method, ''), payment_accounts.type)";
    }

    public static function methodKey(?string $raw, ?string $brand): ?string
    {
        return match (true) {
            $raw === 'bank_fee'                       => null,
            $raw === self::BALANCE                    => self::BALANCE,
            in_array($raw, self::CARD_RAW, true)      => in_array($brand, array_keys(self::BRANDS), true) ? $brand : ($raw === 'mastercard' ? 'mastercard' : 'card'),
            in_array($raw, ['paypal', 'braintree', 'braintree_paypal'], true) => 'paypal',
            isset(self::METHODS[$raw])                => $raw,
            default                                   => 'other',
        };
    }

    private static function shopMethod(string $method): string
    {
        return self::METHODS[$method]['group'] === 'cards' ? 'cards' : $method;
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(ActionRequest $request): array
    {
        $this->groupOrganisations = IndexBankIncoming::accountingOrganisations(group(), $request->user())
            ->where('type', OrganisationTypeEnum::SHOP)->values();
        $this->initialisationFromGroup(group(), $request);
        $this->filters = [
            'organisation' => self::queryString($request, 'org'),
            'shop'         => self::queryString($request, 'shop'),
            'shop_type'    => self::queryString($request, 'shop_type'),
            'brand'        => self::queryString($request, 'brand'),
            'currency'     => self::queryString($request, 'currency'),
            'months'       => (int) ($request->query('months') ?? 12),
            'intercompany' => $request->boolean('intercompany'),
        ];

        return $this->handle($this->group, $this->groupOrganisations, $this->filters);
    }

    private static function queryString(ActionRequest $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->groupOrganisations !== null && $this->groupOrganisations->isNotEmpty();
    }

    public function htmlResponse(array $report, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/GroupPaymentMethods',
            [
                'breadcrumbs'   => array_merge(
                    ShowGroupDashboard::make()->getBreadcrumbs(),
                    [
                        ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']],
                        ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.payment_methods.index'], 'label' => __('Payment methods')]],
                    ]
                ),
                'title'         => __('Payment methods'),
                'pageHead'      => [
                    'title' => __('Payment methods'),
                    'icon'  => ['fal', 'fa-credit-card'],
                ],
                'filters'       => $this->filters,
                'organisations' => $this->groupOrganisations->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->values()->all(),
                'report'        => $report,
            ]
        );
    }
}
