<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\UI;

use App\Actions\Accounting\BankPayee\ClassifyBankOutgoing;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Accounting\BankPayee\BankPayeeCategoryEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Http\Resources\Accounting\BankOutgoingResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Every payment the organisation's banks made, each line in one section by what its payee is. Needs a person lists
 * the payees nobody classified yet, one row each, as a payee is classified once for all its lines.
 */
class IndexBankOutgoing extends OrgAction
{
    use WithBankLines;

    public const array SECTIONS = ['suppliers', 'own_companies', 'staff', 'tax', 'services', 'customer_refunds', 'bank_fees', 'other', 'needs_person'];

    public const string BUCKET_SQL = "coalesce(bank_transactions.out_category, 'needs_person')";

    private string $section = 'needs_person';

    protected function direction(): BankTransactionDirectionEnum
    {
        return BankTransactionDirectionEnum::OUT;
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>}> label and icon by section
     */
    public static function sections(): array
    {
        return [
            'needs_person'  => [__('Needs a person'), ['fal', 'fa-clipboard-list-check']],
            'suppliers'     => [BankPayeeCategoryEnum::SUPPLIERS->label(), ['fal', 'fa-truck-container']],
            'own_companies' => [BankPayeeCategoryEnum::OWN_COMPANIES->label(), ['fal', 'fa-building']],
            'staff'         => [BankPayeeCategoryEnum::STAFF->label(), ['fal', 'fa-user-friends']],
            'tax'           => [BankPayeeCategoryEnum::TAX->label(), ['fal', 'fa-landmark']],
            'services'      => [BankPayeeCategoryEnum::SERVICES->label(), ['fal', 'fa-credit-card']],
            'customer_refunds' => [BankPayeeCategoryEnum::CUSTOMER_REFUNDS->label(), ['fal', 'fa-undo']],
            'bank_fees'     => [BankPayeeCategoryEnum::BANK_FEES->label(), ['fal', 'fa-university']],
            'other'         => [BankPayeeCategoryEnum::OTHER->label(), ['fal', 'fa-dot-circle']],
        ];
    }

    public function handle(Organisation|Group $organisation, string $section = 'needs_person', string $period = '90d', $prefix = null, ?string $currency = null, ?string $from = null, ?string $to = null, ?float $min = null, ?float $max = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('bank_transactions.reference', $value)
                    ->orWhereAnyWordStartWith('bank_transactions.counterparty_name', $value)
                    ->orWhereWith('bank_connections.name', $value);
                $amount = $this->searchedAmount($value);
                if ($amount !== null) {
                    $query->orWhere('bank_transactions.amount', '-'.$amount);
                }
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $lines = $this->bankLines(QueryBuilder::for(BankTransaction::class), $organisation, $period, $currency, $from, $to, $min, $max)
            ->whereRaw('('.self::BUCKET_SQL.') = ?', [$section])
            ->join('bank_connections', 'bank_connections.id', 'bank_transactions.bank_connection_id');

        if ($section === 'needs_person') {
            return $lines
                ->selectRaw('bank_transactions.payee_key, min(bank_transactions.counterparty_name) as counterparty_name, count(*) as lines')
                ->selectRaw('sum('.$this->orgAmountSql().') as org_amount, max(bank_transactions.booked_at) as booked_at')
                ->selectRaw("string_agg(distinct organisations.code, ', ' order by organisations.code) as organisation_code")
                ->selectRaw("string_agg(distinct currencies.code, ', ' order by currencies.code) as currency_code")
                ->selectRaw('(array_agg(bank_transactions.reference order by bank_transactions.booked_at desc) filter (where bank_transactions.reference is not null))[1] as reference')
                ->selectRaw('min(org_currencies.code) as org_currency_code')
                ->groupBy('bank_transactions.payee_key')
                ->defaultSort('-org_amount')
                ->allowedSorts(['org_amount', 'lines', 'booked_at', 'counterparty_name'])
                ->allowedFilters([$globalSearch])
                ->withPaginator($prefix, tableName: request()->route()->getName())
                ->withQueryString();
        }

        return $lines
            ->leftJoin('orders', 'orders.id', 'bank_transactions.order_id')
            ->leftJoin('shops', 'shops.id', 'orders.shop_id')
            ->leftJoin('bank_payees', fn ($join) => $join->on('bank_payees.group_id', 'bank_transactions.group_id')->on('bank_payees.key', 'bank_transactions.payee_key'))
            ->select([
                'bank_transactions.id',
                'bank_transactions.booked_at',
                'bank_transactions.amount',
                'bank_transactions.fee_amount',
                'bank_transactions.counterparty_name',
                'bank_transactions.reference',
                'bank_transactions.provider_type',
                'bank_transactions.payee_key',
                'bank_connections.name as bank',
                'currencies.code as currency_code',
                'org_currencies.code as org_currency_code',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
                'orders.reference as order_reference',
                'orders.slug as order_slug',
                'shops.slug as shop_slug',
                'bank_payees.source as payee_source',
                'bank_payees.model_type as payee_model_type',
            ])
            ->selectRaw($this->orgAmountSql().' as org_amount')
            ->defaultSort('-booked_at')
            ->allowedSorts(['booked_at', AllowedSort::callback('amount', fn ($query, bool $descending) => $query->orderByRaw('bank_transactions.amount '.($descending ? 'asc' : 'desc')))])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->inSection($organisation, $request);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $slug = $request->query('org');
        $this->forGroup(group(), $request->user(), is_string($slug) ? $slug : null);
        $this->initialisationFromGroup(group(), $request);

        return $this->inSection(group(), $request);
    }

    private function inSection(Organisation|Group $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->section = str($request->route()->getName())->afterLast('.')->toString();
        abort_unless(in_array($this->section, self::SECTIONS, true), 404);
        $this->readFilters($organisation, $request);

        return $this->handle($organisation, $this->section, $this->period, currency: $this->currency, from: $this->from, to: $this->to, min: $this->min, max: $this->max);
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    public static function subNavigation(Organisation|Group $organisation, array $counts, array $query = []): array
    {
        return self::sectionsNavigation($organisation, 'bank_outgoing', self::sections(), $counts, $query);
    }

    public function htmlResponse(LengthAwarePaginator $bankTransactions, ActionRequest $request): Response
    {
        $parent        = $this->organisationIds === null ? $this->organisation : $this->group;
        $isGroup       = $parent instanceof Group;
        $sectionTotals = $this->sectionTotals($parent, $this->period, $this->currency, $this->from, $this->to, $this->min, $this->max);
        $title         = self::sections()[$this->section][0];

        return Inertia::render(
            'Org/Accounting/BankOutgoing',
            [
                'breadcrumbs'    => self::breadcrumbs($parent, $request->route()->originalParameters()),
                'title'          => $title,
                'pageHead'       => [
                    'model'         => __('Bank outgoing'),
                    'title'         => $title,
                    'icon'          => ['fal', 'fa-university'],
                    'subNavigation' => self::subNavigation($parent, array_map(fn (array $totals) => $totals['count'], $sectionTotals), $this->filtersQuery()),
                ],
                'canEdit'        => !$isGroup && $request->user()->authTo("accounting.{$this->organisation->id}.edit"),
                'organisationId' => $isGroup ? null : $this->organisation->id,
                'organisation'   => $this->organisationSlug,
                'organisations'  => $isGroup ? self::organisationOptions($this->group, $request->user()) : [],
                'section'        => $this->section,
                'sectionTotals'  => $sectionTotals,
                'summary'        => $sectionTotals[$this->section],
                'categories'     => collect(BankPayeeCategoryEnum::cases())
                    ->reject(fn (BankPayeeCategoryEnum $category) => $category === BankPayeeCategoryEnum::BANK_FEES)
                    ->map(fn (BankPayeeCategoryEnum $category) => ['value' => $category->value, 'label' => $category->label(), 'link_types' => $category->linkTypes()])
                    ->values()->all(),
                'currencyTotals' => $this->currencyTotals($parent, $this->section, $this->period, $this->from, $this->to, $this->min, $this->max),
                'period'         => $this->from ? null : $this->period,
                'currency'       => $this->currency,
                'currencies'     => $this->currencies($parent),
                'from'           => $this->from,
                'to'             => $this->to,
                'min'            => $this->min,
                'max'            => $this->max,
                'orgCurrency'    => $parent->currency->code,
                'data'           => BankOutgoingResource::collection($bankTransactions),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $isGroup = $this->organisationIds !== null;
            $table->withGlobalSearch()
                ->withEmptyState([
                    'title'       => $this->section === 'needs_person' ? __('Nothing waiting for a person') : __('No payments'),
                    'description' => $this->section === 'needs_person' ? __('Every payee the banks paid under these filters is classified.') : __('The banks paid nothing of this kind under these filters.'),
                    'count'       => 0,
                ]);

            if ($this->section === 'needs_person') {
                $table->column(key: 'counterparty_name', label: __('Payee'), sortable: true);
                if ($isGroup) {
                    $table->column(key: 'organisation_code', label: __('Organisation'));
                }
                $table->column(key: 'lines', label: __('Payments'), sortable: true, align: 'right');
                $table->column(key: 'org_amount', label: __('Paid'), sortable: true, align: 'right');
                $table->column(key: 'booked_at', label: __('Last paid'), sortable: true);
                $table->column(key: 'reference', label: __('Last reference'));
            } else {
                $table->column(key: 'booked_at', label: __('Date'), sortable: true);
                if ($isGroup) {
                    $table->column(key: 'organisation_code', label: __('Organisation'));
                }
                $table->column(key: 'bank', label: __('Bank'));
                $table->column(key: 'amount', label: __('Amount'), sortable: true, align: 'right');
                $table->column(key: 'counterparty_name', label: __('Paid to'));
                $table->column(key: 'reference', label: __('Bank reference'));
                if ($this->section === 'customer_refunds') {
                    $table->column(key: 'order_reference', label: __('Order'));
                }
                $table->column(key: 'kind', label: __('Kind'));
            }
            $table->column(key: 'actions', label: '');
        };
    }

    /**
     * @param array<string, mixed> $routeParameters
     * @return array<int, array<string, mixed>>
     */
    public static function breadcrumbs(Organisation|Group $parent, array $routeParameters): array
    {
        if ($parent instanceof Group) {
            return array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']],
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.bank_outgoing.index'], 'label' => __('Bank outgoing')]],
                ]
            );
        }

        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.org.accounting.bank_outgoing.index', 'parameters' => $routeParameters], 'label' => __('Bank outgoing')]],
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            'TRANSFER'                           => __('Transfer'),
            'CARD_PAYMENT'                       => __('Card payment'),
            'DIRECT_DEBIT_TRANSACTION'           => __('Direct debit'),
            ClassifyBankOutgoing::OWN_BALANCE_TYPE => __('Between our balances'),
            'CASH_WITHDRAWAL'                    => __('Cash withdrawal'),
            'BALANCE_ADJUSTMENT'                 => __('Adjustment by the bank'),
            'BALANCE_ASSET_FEE'                  => __('Fee for assets held'),
            'BALANCE_HOLD_FEE'                   => __('Fee for holding a balance'),
            'CARD_ORDER'                         => __('Card order'),
            'BANK_DETAILS_ORDER'                 => __('Bank details order'),
        ];
    }
}
