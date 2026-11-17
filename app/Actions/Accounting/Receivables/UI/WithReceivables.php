<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Receivables\UI;

use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * What the Receivables pages share. An invoice is owed when it is an invoice (not a refund), not deleted, not in
 * process, its pay status is unpaid (invoices of unknown pay status, older than the shop's cut-off and without
 * payments, are left out) and what is left after its payments and the unpaid part of its refunds is above zero.
 * An invoice of a marketplace shop is owed by the marketplace, not by the customer, until the payout of its order
 * is found in the bank. An invoice to a company of the group is owed between our companies: it counts for the
 * organisation and is left out of what customers owe the group. An unpaid invoice migrated from Aurora (it has a
 * source id) is legacy, not verified: it is counted apart from all of the above. An invoice is due on its date plus the customer's payment terms, on its date without terms.
 * Amounts are converted at the rate kept on the invoice. Customer balances are a separate figure, never netted.
 */
trait WithReceivables
{
    use WithAccountingPagesAuthorisation {
        authorize as authorizeInOrganisation;
    }

    public const array AGES = ['not_due', '1_30', '31_60', '61_90', 'over_90'];

    public const array OWED_BY = ['customers', 'companies', 'marketplaces', 'legacy'];

    public const string OWED_SQL = '(invoices.total_amount - invoices.payment_amount + coalesce(refunded.amount, 0))';

    /** @var Collection<int, Organisation>|null */
    protected ?Collection $groupOrganisations = null;

    /** @var Collection<int, Organisation> */
    protected Collection $readOrganisations;

    protected ?string $organisationSlug = null;

    protected function readOrganisation(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);
        $this->readOrganisations = collect([$organisation]);

        return $organisation;
    }

    protected function readGroup(ActionRequest $request): Group
    {
        $this->groupOrganisations = IndexBankIncoming::accountingOrganisations(group(), $request->user());
        $this->initialisationFromGroup(group(), $request);
        $picked                   = is_string($request->query('org')) ? $this->groupOrganisations->firstWhere('slug', $request->query('org')) : null;
        $this->organisationSlug   = $picked?->slug;
        $this->readOrganisations  = $picked ? collect([$picked]) : $this->groupOrganisations;

        return $this->group;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $this->groupOrganisations === null) {
            return $this->authorizeInOrganisation($request);
        }

        return $this->groupOrganisations->isNotEmpty();
    }

    protected function isGroup(): bool
    {
        return $this->groupOrganisations !== null;
    }

    protected function parent(): Organisation|Group
    {
        return $this->isGroup() ? $this->group : $this->organisation;
    }

    public static function daysOverdueSql(Carbon $today): string
    {
        return "(date '{$today->toDateString()}' - (invoices.date::date + coalesce(customers.payment_terms_days, 0)))";
    }

    public static function ageSql(Carbon $today): string
    {
        $days = self::daysOverdueSql($today);

        return "case when $days <= 0 then 'not_due' when $days <= 30 then '1_30' when $days <= 60 then '31_60' when $days <= 90 then '61_90' else 'over_90' end";
    }

    public static function convertedSql(Organisation|Group $parent): string
    {
        return self::OWED_SQL.' * invoices.'.($parent instanceof Group ? 'grp_exchange' : 'org_exchange');
    }

    /**
     * @template TQuery of Builder
     *
     * @param TQuery $query a query on invoices
     * @param Collection<int, Organisation> $organisations
     * @param string|null $owedBy customers, companies (of the group), marketplaces or legacy (migrated from Aurora); all of them when null
     * @return TQuery
     */
    public static function owedInvoices(Builder $query, Organisation|Group $parent, Collection $organisations, ?string $owedBy = null): Builder
    {
        $refunded = DB::table('invoices as refunds')
            ->selectRaw('sum(refunds.total_amount - refunds.payment_amount) as amount')
            ->whereColumn('refunds.original_invoice_id', 'invoices.id')
            ->where('refunds.type', InvoiceTypeEnum::REFUND->value)
            ->where('refunds.in_process', false)
            ->whereNull('refunds.deleted_at');

        return $query
            ->join('shops', 'shops.id', 'invoices.shop_id')
            ->join('customers', 'customers.id', 'invoices.customer_id')
            ->join('currencies', 'currencies.id', 'invoices.currency_id')
            ->join('organisations', 'organisations.id', 'invoices.organisation_id')
            ->leftJoinLateral($refunded, 'refunded')
            ->whereIn('invoices.organisation_id', $organisations->pluck('id'))
            ->where('invoices.pay_status', InvoicePayStatusEnum::UNPAID->value)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE->value)
            ->where('invoices.in_process', false)
            ->whereNull('invoices.deleted_at')
            ->whereRaw(self::OWED_SQL.' > 0')
            ->whereRaw("not (shops.type = ? and exists (select 1 from bank_transactions where bank_transactions.order_id = invoices.order_id and bank_transactions.match_state = ?))", [ShopTypeEnum::EXTERNAL->value, BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE->value])
            ->whereRaw("not exists (select 1 from bank_transactions where bank_transactions.match_state = ? and bank_transactions.match_data->'order_ids' @> to_jsonb(invoices.order_id))", [BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE->value])
            ->when($owedBy === 'legacy', fn ($query) => $query->whereNotNull('invoices.source_id'))
            ->when($owedBy !== null && $owedBy !== 'legacy', fn ($query) => $query->whereNull('invoices.source_id')->where('shops.type', $owedBy === 'marketplaces' ? '=' : '!=', ShopTypeEnum::EXTERNAL->value))
            ->when($owedBy === 'companies', fn ($query) => $query->whereNotNull('invoices.as_organisation_id'))
            ->when($owedBy === 'customers' && $parent instanceof Group, fn ($query) => $query->whereNull('invoices.as_organisation_id'));
    }

    /**
     * A customer balance in the currency of the page, at the last rate known.
     */
    public static function convertedBalanceSql(Organisation|Group $parent): string
    {
        $target = (int) $parent->currency_id;
        $pivot  = (int) Currency::where('code', config('app.currency_exchange.pivot'))->value('id');
        $rate   = fn (string $currencyId) => "case when $currencyId = $pivot then 1 else (select ce.exchange from currency_exchanges ce where ce.currency_id = $currencyId order by ce.date desc limit 1) end";

        return "customers.balance * case when shops.currency_id = $target then 1 else ({$rate((string) $target)}) / nullif(({$rate('shops.currency_id')}), 0) end";
    }

    /**
     * @param Collection<int, Organisation> $organisations
     */
    public static function customerBalances(Collection $organisations): \Illuminate\Database\Eloquent\Builder
    {
        return Customer::query()
            ->join('shops', 'shops.id', 'customers.shop_id')
            ->join('currencies', 'currencies.id', 'shops.currency_id')
            ->join('organisations', 'organisations.id', 'customers.organisation_id')
            ->whereIn('customers.organisation_id', $organisations->pluck('id'))
            ->where('customers.balance', '!=', 0);
    }

    /**
     * @return array{invoices: int, debtors: int, balances: int, cash_on_delivery: int}
     */
    protected function sectionCounts(): array
    {
        $owed = self::owedInvoices(DB::table('invoices'), $this->parent(), $this->readOrganisations, 'customers')
            ->selectRaw('count(*) as invoices, count(distinct invoices.customer_id) as debtors, count(*) filter (where invoices.is_cash_on_delivery and exists (select 1 from orders where orders.id = invoices.order_id and orders.dispatched_at is not null)) as cash_on_delivery')
            ->first();

        return [
            'invoices' => (int) $owed->invoices,
            'debtors'  => (int) $owed->debtors,
            'balances' => self::customerBalances($this->readOrganisations)->count(),
            'cash_on_delivery' => (int) $owed->cash_on_delivery,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function subNavigation(): array
    {
        return IndexBankIncoming::sectionsNavigation($this->parent(), 'receivables', [
            'invoices' => [__('Unpaid invoices'), ['fal', 'fa-file-invoice-dollar']],
            'debtors'  => [__('Debtors'), ['fal', 'fa-user-clock']],
            'balances' => [__('Customer balances'), ['fal', 'fa-piggy-bank']],
            'cash_on_delivery' => [__('Cash on delivery not paid'), ['fal', 'fa-truck']],
        ], $this->sectionCounts(), array_filter(['org' => $this->organisationSlug]));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function organisationOptions(): array
    {
        return $this->isGroup() ? $this->groupOrganisations->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->values()->all() : [];
    }

    /**
     * @return array{name: string, parameters: array<int, string>}
     */
    public static function customerRoute(object $row): array
    {
        return $row->shop_type === ShopTypeEnum::FULFILMENT->value
            ? ['name' => 'grp.org.fulfilments.show.crm.customers.show', 'parameters' => [$row->organisation_slug, $row->fulfilment_slug, $row->slug]]
            : ['name' => 'grp.org.shops.show.crm.customers.show', 'parameters' => [$row->organisation_slug, $row->shop_slug, $row->slug]];
    }

    /**
     * @return array<string, array{name: string, parameters: array<string, string>}>
     */
    protected function sectionRoutes(): array
    {
        $base       = $this->isGroup() ? 'grp.financials.receivables.' : 'grp.org.accounting.receivables.';
        $parameters = $this->isGroup() ? array_filter(['org' => $this->organisationSlug]) : ['organisation' => $this->organisation->slug];

        return collect(['index', ...IndexReceivables::SECTIONS])->mapWithKeys(fn (string $section) => [$section => ['name' => $base.$section, 'parameters' => $parameters]])->all();
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        if ($this->isGroup()) {
            return array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']],
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.receivables.index'], 'label' => __('Receivables')]],
                ]
            );
        }

        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.org.accounting.receivables.index', 'parameters' => $routeParameters], 'label' => __('Receivables')]],
            ]
        );
    }
}
