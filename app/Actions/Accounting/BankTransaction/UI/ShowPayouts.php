<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 11:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\UI;

use App\Actions\Accounting\Receivables\UI\ShowReceivablesDashboard;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * What payment providers (Checkout, PastPay, cash on delivery carriers) and marketplaces did with the money customers
 * paid through them, one row per provider, organisation and currency (per shop for a marketplace). Paid, paid out,
 * fees and payouts not reconciled are for the period; what they still hold is as of today.
 * Held is only given where the data can tell it:
 * - Checkout and PastPay: what is left after refunds of the payments made since the first one a reconciled payout
 *   settled, that no reconciled payout has settled yet (a PastPay payment only once its order is invoiced, as PastPay
 *   pays invoices). A stream whose
 *   payouts never reach a connected bank is "not seen"; one whose payouts arrive but were never reconciled is "not read".
 * - Cash on delivery: the unpaid cash on delivery invoices, the money carriers still have to pay us; shown apart as
 *   "not seen" in a currency whose carriers' payouts never reach a connected bank.
 * - Marketplaces: the unpaid invoices of the shop, as Receivables counts them; "not seen" when no payout of the shop
 *   ever reached a connected bank.
 * Fees are what a reconciled payout says the provider and the bank kept, and what a marketplace kept of the order total.
 */
class ShowPayouts extends OrgAction
{
    use WithBankLines;

    public const array PROVIDERS = ['checkout', 'pastpay', 'cod'];

    /** ponytail: matches the 90 days MatchBankTransactions looks back for PastPay; older unsettled money is shown apart, as it was most likely paid in a payout that did not reconcile. */
    public const int HELD_DAYS = 90;

    private const string NOT_RECONCILED_SQL = "(bank_transactions.match_state = 'unmatched'
        or bank_transactions.match_data->>'reconciled_at' is null
        or jsonb_array_length(coalesce(bank_transactions.match_data->'payments_missing', '[]'::jsonb)) > 0
        or jsonb_array_length(coalesce(bank_transactions.match_data->'differences', '[]'::jsonb)) > 0)";

    private const string FEES_SQL = "case when bank_transactions.match_data->>'reconciled_at' is null then 0 else coalesce((bank_transactions.match_data->>'processor_fees')::numeric, 0) + coalesce((bank_transactions.match_data->>'bank_fee')::numeric, 0) end";

    /** What the marketplace kept: the order total (owed plus commission, or the order total old backfilled Faire payouts keep) less what was received. */
    private const string MARKETPLACE_FEES_SQL = "coalesce(coalesce((bank_transactions.match_data->>'order_total')::numeric, (bank_transactions.match_data->>'owed')::numeric + coalesce((bank_transactions.match_data->>'commission')::numeric, 0)) - (bank_transactions.match_data->>'received')::numeric, (bank_transactions.match_data->>'commission')::numeric, 0)";

    private const string MARKETPLACE_NOT_RECONCILED_SQL = "(bank_transactions.match_state = 'unmatched' or abs(coalesce((bank_transactions.match_data->>'difference')::numeric, 0)) > 0.01)";

    /** @var Collection<int, Organisation>|null */
    private ?Collection $groupOrganisations = null;

    /**
     * @param Collection<int, Organisation> $organisations
     * @return array<string, mixed>
     */
    public function handle(Organisation|Group $parent, Collection $organisations, string $period = '90d'): array
    {
        if ($parent instanceof Group) {
            $this->organisationIds ??= $organisations->pluck('id')->all();
            $this->groupCurrencyId ??= $parent->currency_id;
        }
        $since = match ($period) {
            'all'   => null,
            '1y'    => now()->subYear(),
            default => now()->subDays(90),
        };

        $rows = $this->providerRows($parent, $organisations, $period, $since)
            ->concat($this->marketplaceRows($parent, $organisations, $period, $since))
            ->sortBy(fn (array $row) => [array_search($row['provider'], [...self::PROVIDERS, 'marketplace']), $row['organisation_code'], -$row['paid']['amount']])
            ->values();

        $sum = fn (string $field, ?callable $filter = null) => round((float) $rows->filter($filter ?? fn () => true)->sum($field), 2);

        return [
            'period' => $period,
            'held_days' => self::HELD_DAYS,
            'rows'   => $rows->all(),
            'totals' => [
                'paid'           => $sum('paid.amount'),
                'refunded'       => $sum('refunded.amount'),
                'paid_out'       => $sum('paid_out.amount'),
                'fees'           => $sum('fees.amount'),
                'fees_unknown'   => (int) $rows->sum('fees.unknown'),
                'held'           => $sum('held.amount', fn (array $row) => $row['held']['status'] === 'known'),
                'held_stale'     => $sum('held.stale.amount', fn (array $row) => $row['held']['status'] === 'known'),
                'held_count'     => (int) $rows->where('held.status', 'known')->sum('held.count'),
                'not_seen'       => $rows->where('held.status', '!=', 'known')->count(),
                'not_seen_unpaid' => round($sum('held.amount', fn (array $row) => $row['held']['status'] !== 'known') + $sum('held.stale.amount', fn (array $row) => $row['held']['status'] !== 'known'), 2),
                'not_reconciled' => ['count' => (int) $rows->sum('not_reconciled.count'), 'amount' => $sum('not_reconciled.amount')],
            ],
        ];
    }

    /**
     * @param Collection<int, Organisation> $organisations
     * @return Collection<int, array<string, mixed>>
     */
    private function providerRows(Organisation|Group $parent, Collection $organisations, string $period, ?Carbon $since): Collection
    {
        $providerSql = "case payment_accounts.type when '".PaymentAccountTypeEnum::CASH_ON_DELIVERY->value."' then 'cod' else payment_accounts.type end";
        $amountField = $parent instanceof Group ? 'grp_amount' : 'org_amount';

        $paid = $this->providerPayments($organisations)
            ->when($since, fn ($query) => $query->where('payments.date', '>=', $since))
            ->selectRaw("$providerSql as provider, payments.organisation_id, currencies.code as currency")
            ->selectRaw("count(*) filter (where payments.type = 'payment') as payments, sum(payments.amount) filter (where payments.type = 'payment') as paid, sum(payments.$amountField) filter (where payments.type = 'payment') as paid_amount")
            ->selectRaw("-sum(payments.amount) filter (where payments.type = 'refund') as refunded, -sum(payments.$amountField) filter (where payments.type = 'refund') as refunded_amount")
            ->groupByRaw('1, 2, 3')
            ->get()
            ->keyBy(fn (object $row) => "$row->provider|$row->organisation_id|$row->currency");

        $orgAmount   = $this->orgAmountSql();
        $channelSql  = IndexBankIncoming::PROVIDER_SQL;
        $familySql   = "case when ($channelSql) in ('checkout', 'pastpay') then ($channelSql) else 'cod' end";
        $fees        = self::FEES_SQL;
        $payoutLines = $this->bankLines(BankTransaction::query(), $parent, $period, null, null, null)
            ->whereRaw('('.IndexBankIncoming::BUCKET_SQL.') = ?', ['payment_accounts'])
            ->selectRaw("$familySql as provider, ($channelSql) as channel, bank_transactions.organisation_id, currencies.code as currency")
            ->selectRaw("count(*) as lines, sum(bank_transactions.amount) as native, sum($orgAmount) as amount")
            ->selectRaw("sum($fees) as fees, sum(($fees) * ($orgAmount) / nullif(bank_transactions.amount, 0)) as fees_amount")
            ->selectRaw("count(*) filter (where bank_transactions.match_data->>'reconciled_at' is null) as fees_unknown")
            ->selectRaw('count(*) filter (where '.self::NOT_RECONCILED_SQL.') as not_reconciled, sum('.$orgAmount.') filter (where '.self::NOT_RECONCILED_SQL.') as not_reconciled_amount')
            ->groupByRaw('1, 2, 3, 4')
            ->toBase()
            ->get()
            ->groupBy(fn (object $row) => "$row->provider|$row->organisation_id|$row->currency");

        $seen = BankTransaction::query()
            ->whereIn('bank_transactions.organisation_id', $organisations->pluck('id'))
            ->whereRaw('('.IndexBankIncoming::BUCKET_SQL.') = ?', ['payment_accounts'])
            ->join('currencies', 'currencies.id', 'bank_transactions.currency_id')
            ->selectRaw("$familySql as provider, bank_transactions.organisation_id, currencies.code as currency, count(*) filter (where bank_transactions.match_data->>'reconciled_at' is not null) as reconciled")
            ->groupByRaw('1, 2, 3')
            ->toBase()
            ->get()
            ->keyBy(fn (object $row) => "$row->provider|$row->organisation_id|$row->currency");

        $held = $this->heldByProviders($parent, $organisations)
            ->concat($this->heldCashOnDelivery($parent, $organisations))
            ->keyBy(fn (object $row) => "$row->provider|$row->organisation_id|$row->currency");

        $labels        = IndexBankIncoming::providerLabels();
        $organisations = $organisations->keyBy('id');

        return $paid->keys()->concat($payoutLines->keys())->concat($held->keys())->unique()->map(function (string $key) use ($paid, $payoutLines, $seen, $held, $labels, $organisations, $parent) {
            [$provider, $organisationId, $currency] = explode('|', $key);
            $organisation = $organisations->get((int) $organisationId);
            $lines        = $payoutLines->get($key, collect());
            $paidRow      = $paid->get($key);
            $heldRow      = $held->get($key);
            $seenRow      = $seen->get($key);
            $status       = match (true) {
                $seenRow === null => 'not_seen',
                $provider !== 'cod' && (int) $seenRow->reconciled === 0 => 'not_read',
                default => 'known',
            };

            return [
                'key'               => $key,
                'kind'              => 'provider',
                'provider'          => $provider,
                'label'             => $labels[$provider] ?? $provider,
                'organisation_code' => $organisation?->code,
                'currency'          => $currency,
                'paid'              => ['native' => round((float) ($paidRow->paid ?? 0), 2), 'amount' => round((float) ($paidRow->paid_amount ?? 0), 2), 'count' => (int) ($paidRow->payments ?? 0)],
                'refunded'          => ['native' => round((float) ($paidRow->refunded ?? 0), 2), 'amount' => round((float) ($paidRow->refunded_amount ?? 0), 2)],
                'paid_out'          => ['native' => round((float) $lines->sum('native'), 2), 'amount' => round((float) $lines->sum('amount'), 2), 'count' => (int) $lines->sum('lines')],
                'channels'          => $provider === 'cod' ? $lines->map(fn (object $line) => ['key' => $line->channel, 'label' => $labels[$line->channel] ?? $line->channel, 'native' => round((float) $line->native, 2), 'count' => (int) $line->lines])->values()->all() : [],
                'fees'              => ['native' => round((float) $lines->sum('fees'), 2), 'amount' => round((float) $lines->sum('fees_amount'), 2), 'unknown' => $provider === 'cod' ? 0 : (int) $lines->sum('fees_unknown')],
                'held'              => self::heldFigure($status, $status === 'known' || $provider === 'cod' ? $heldRow : null),
                'not_reconciled'    => ['count' => (int) $lines->sum('not_reconciled'), 'amount' => round((float) $lines->sum('not_reconciled_amount'), 2)],
                'route'             => $lines->isEmpty() ? null : $this->bankIncomingRoute($parent, $organisation, 'payment_accounts', $provider === 'cod' && $lines->count() === 1 ? $lines->first()->channel : ($provider === 'cod' ? null : $provider)),
            ];
        })->values();
    }

    /**
     * @param Collection<int, Organisation> $organisations
     */
    private function providerPayments(Collection $organisations): \Illuminate\Database\Query\Builder
    {
        return DB::table('payments')
            ->join('payment_accounts', 'payment_accounts.id', 'payments.payment_account_id')
            ->join('currencies', 'currencies.id', 'payments.currency_id')
            ->whereIn('payments.organisation_id', $organisations->pluck('id'))
            ->whereIn('payment_accounts.type', [PaymentAccountTypeEnum::CHECKOUT->value, PaymentAccountTypeEnum::PASTPAY->value, PaymentAccountTypeEnum::CASH_ON_DELIVERY->value])
            ->where('payments.status', PaymentStatusEnum::SUCCESS->value)
            ->where('payments.state', PaymentStateEnum::COMPLETED->value);
    }

    /**
     * Checkout and PastPay payments no reconciled payout has settled, from the first payment one settled.
     *
     * @param Collection<int, Organisation> $organisations
     * @return Collection<int, object>
     */
    private function heldByProviders(Organisation|Group $parent, Collection $organisations): Collection
    {
        $settled = DB::table('bank_transactions')
            ->crossJoin(DB::raw("lateral jsonb_array_elements_text(bank_transactions.match_data->'payment_ids') as settled(payment_id)"))
            ->whereIn('bank_transactions.organisation_id', $organisations->pluck('id'))
            ->where('bank_transactions.match_state', BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT->value)
            ->whereRaw("bank_transactions.match_data->>'reconciled_at' is not null")
            ->whereRaw("jsonb_typeof(bank_transactions.match_data->'payment_ids') = 'array'")
            ->selectRaw('distinct settled.payment_id::bigint as payment_id');

        $amountField = $parent instanceof Group ? 'grp_amount' : 'org_amount';
        $held        = function (PaymentAccountTypeEnum $type) use ($organisations, $settled, $amountField) {
            $payments = fn () => $this->providerPayments($organisations)
                ->where('payments.type', 'payment')
                ->where('payment_accounts.type', $type->value);

            $starts = $payments()
                ->joinSub($settled, 'settled', 'settled.payment_id', 'payments.id')
                ->selectRaw('payments.organisation_id, payments.currency_id, min(payments.date) as since')
                ->groupBy(['payments.organisation_id', 'payments.currency_id']);

            return $payments()
                ->joinSub($starts, 'starts', fn ($join) => $join->on('starts.organisation_id', 'payments.organisation_id')->on('starts.currency_id', 'payments.currency_id'))
                ->leftJoinSub($settled, 'settled', 'settled.payment_id', 'payments.id')
                ->whereNull('settled.payment_id')
                ->whereColumn('payments.date', '>=', 'starts.since')
                ->whereRaw('payments.amount - coalesce(payments.total_refund, 0) > 0')
                ->when($type === PaymentAccountTypeEnum::PASTPAY, fn ($query) => $query->whereExists(
                    fn ($query) => $query->selectRaw('1')->from('model_has_payments')
                        ->join('invoices', 'invoices.order_id', 'model_has_payments.model_id')
                        ->whereColumn('model_has_payments.payment_id', 'payments.id')
                        ->where('model_has_payments.model_type', 'Order')
                        ->where('invoices.type', InvoiceTypeEnum::INVOICE->value)
                        ->whereNull('invoices.deleted_at')
                        ->where('invoices.in_process', false)
                ))
                ->selectRaw("'$type->value' as provider, payments.organisation_id, currencies.code as currency, ".self::heldColumns('payments.date', '(payments.amount - coalesce(payments.total_refund, 0))', "(payments.$amountField * (payments.amount - coalesce(payments.total_refund, 0)) / payments.amount)"))
                ->groupBy(['payments.organisation_id', 'currencies.code'])
                ->get();
        };

        return $held(PaymentAccountTypeEnum::CHECKOUT)->concat($held(PaymentAccountTypeEnum::PASTPAY));
    }

    /**
     * @param Collection<int, Organisation> $organisations
     * @return Collection<int, object>
     */
    private function heldCashOnDelivery(Organisation|Group $parent, Collection $organisations): Collection
    {
        return ShowReceivablesDashboard::owedInvoices(DB::table('invoices'), $parent, $organisations, 'customers')
            ->where('invoices.is_cash_on_delivery', true)
            ->selectRaw("'cod' as provider, invoices.organisation_id, currencies.code as currency, ".self::heldColumns('invoices.date', ShowReceivablesDashboard::OWED_SQL, ShowReceivablesDashboard::convertedSql($parent)))
            ->groupBy(['invoices.organisation_id', 'currencies.code'])
            ->get();
    }

    /**
     * @param Collection<int, Organisation> $organisations
     * @return Collection<int, array<string, mixed>>
     */
    private function marketplaceRows(Organisation|Group $parent, Collection $organisations, string $period, ?Carbon $since): Collection
    {
        $exchange = 'invoices.'.($parent instanceof Group ? 'grp_exchange' : 'org_exchange');

        $invoiced = DB::table('invoices')
            ->join('shops', 'shops.id', 'invoices.shop_id')
            ->whereIn('invoices.organisation_id', $organisations->pluck('id'))
            ->where('shops.type', ShopTypeEnum::EXTERNAL->value)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE->value)
            ->where('invoices.in_process', false)
            ->whereNull('invoices.deleted_at')
            ->when($since, fn ($query) => $query->where('invoices.date', '>=', $since))
            ->selectRaw("shops.id as shop_id, count(*) as count, sum(invoices.total_amount) as native, sum(invoices.total_amount * $exchange) as amount")
            ->groupBy('shops.id')
            ->get()
            ->keyBy('shop_id');

        $orgAmount = $this->orgAmountSql();
        $payouts   = $this->bankLines(BankTransaction::query(), $parent, $period, null, null, null)
            ->whereRaw('('.IndexBankIncoming::BUCKET_SQL.') = ?', ['marketplaces'])
            ->leftJoin('orders', 'orders.id', 'bank_transactions.order_id')
            ->selectRaw("orders.shop_id, bank_transactions.organisation_id, currencies.code as currency, count(*) as lines, sum(bank_transactions.amount) as native, sum($orgAmount) as amount")
            ->selectRaw('sum('.self::MARKETPLACE_FEES_SQL.') as fees, sum(('.self::MARKETPLACE_FEES_SQL.") * ($orgAmount) / nullif(bank_transactions.amount, 0)) as fees_amount")
            ->selectRaw('count(*) filter (where '.self::MARKETPLACE_NOT_RECONCILED_SQL.') as not_reconciled, sum('.$orgAmount.') filter (where '.self::MARKETPLACE_NOT_RECONCILED_SQL.') as not_reconciled_amount')
            ->groupByRaw('1, 2, 3')
            ->toBase()
            ->get();

        $held = ShowReceivablesDashboard::owedInvoices(DB::table('invoices'), $parent, $organisations, 'marketplaces')
            ->selectRaw('invoices.shop_id, '.self::heldColumns('invoices.date', ShowReceivablesDashboard::OWED_SQL, ShowReceivablesDashboard::convertedSql($parent)))
            ->groupBy('invoices.shop_id')
            ->get()
            ->keyBy('shop_id');

        $paidOutShops = DB::table('bank_transactions')
            ->join('orders', 'orders.id', 'bank_transactions.order_id')
            ->whereIn('bank_transactions.organisation_id', $organisations->pluck('id'))
            ->where('bank_transactions.match_state', BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE->value)
            ->distinct()
            ->pluck('orders.shop_id')
            ->flip();

        $shopIds = $invoiced->keys()->concat($held->keys())->concat($payouts->pluck('shop_id')->filter())->unique();
        $shops   = DB::table('shops')->join('currencies', 'currencies.id', 'shops.currency_id')->whereIn('shops.id', $shopIds)
            ->get(['shops.id', 'shops.name', 'shops.code', 'shops.organisation_id', 'currencies.code as currency'])
            ->keyBy('id');

        $organisations = $organisations->keyBy('id');
        $row           = function (?object $shop, Collection $lines, ?object $invoicedRow, ?object $heldRow, ?int $organisationId, ?string $currency) use ($paidOutShops, $organisations, $parent) {
            $status       = $shop && $paidOutShops->has($shop->id) ? 'known' : 'not_seen';
            $organisation = $organisations->get($organisationId);

            return [
                'key'               => 'marketplace|'.($shop->id ?? "unknown|$organisationId|$currency"),
                'kind'              => 'marketplace',
                'provider'          => 'marketplace',
                'label'             => $shop ? $shop->name : __('Marketplace order not found'),
                'shop_code'         => $shop?->code,
                'organisation_code' => $organisation?->code,
                'currency'          => $currency,
                'paid'              => ['native' => round((float) ($invoicedRow->native ?? 0), 2), 'amount' => round((float) ($invoicedRow->amount ?? 0), 2), 'count' => (int) ($invoicedRow->count ?? 0)],
                'refunded'          => ['native' => 0.0, 'amount' => 0.0],
                'paid_out'          => ['native' => round((float) $lines->sum('native'), 2), 'amount' => round((float) $lines->sum('amount'), 2), 'count' => (int) $lines->sum('lines')],
                'channels'          => [],
                'fees'              => ['native' => round((float) $lines->sum('fees'), 2), 'amount' => round((float) $lines->sum('fees_amount'), 2), 'unknown' => 0],
                'held'              => self::heldFigure($shop ? $status : 'not_seen', $heldRow),
                'not_reconciled'    => ['count' => (int) $lines->sum('not_reconciled'), 'amount' => round((float) $lines->sum('not_reconciled_amount'), 2)],
                'route'             => $lines->isEmpty() ? null : $this->bankIncomingRoute($parent, $organisation, 'marketplaces'),
            ];
        };

        $known = $shopIds->map(fn (int $shopId) => $shops->get($shopId))->filter()->map(fn (object $shop) => $row(
            $shop,
            $payouts->where('shop_id', $shop->id),
            $invoiced->get($shop->id),
            $held->get($shop->id),
            $shop->organisation_id,
            $shop->currency
        ));

        $unknown = $payouts->whereNull('shop_id')->map(fn (object $line) => $row(null, collect([$line]), null, null, $line->organisation_id, $line->currency));

        return $known->concat($unknown)->values();
    }

    /**
     * What is held from the last HELD_DAYS, and apart what has been waiting longer.
     */
    private static function heldColumns(string $date, string $native, string $amount): string
    {
        $recent = "$date >= date '".now()->subDays(self::HELD_DAYS)->toDateString()."'";

        return "count(*) filter (where $recent) as count, sum($native) filter (where $recent) as native, sum($amount) filter (where $recent) as amount, (min($date) filter (where $recent))::date as oldest,
            count(*) filter (where not ($recent)) as stale_count, sum($native) filter (where not ($recent)) as stale_native, sum($amount) filter (where not ($recent)) as stale_amount";
    }

    /**
     * @return array{status: string, native: float|null, amount: float|null, count: int, oldest: string|null, stale: array{count: int, native: float, amount: float}}
     */
    private static function heldFigure(string $status, ?object $row): array
    {
        $unknown = $status !== 'known' && $row === null;

        return [
            'status' => $status,
            'native' => $unknown ? null : round((float) ($row->native ?? 0), 2),
            'amount' => $unknown ? null : round((float) ($row->amount ?? 0), 2),
            'count'  => (int) ($row->count ?? 0),
            'oldest' => $row->oldest ?? null,
            'stale'  => ['count' => (int) ($row->stale_count ?? 0), 'native' => round((float) ($row->stale_native ?? 0), 2), 'amount' => round((float) ($row->stale_amount ?? 0), 2)],
        ];
    }

    /**
     * @return array{name: string, parameters: array<string, string>}
     */
    private function bankIncomingRoute(Organisation|Group $parent, ?Organisation $organisation, string $section, ?string $provider = null): array
    {
        $query = array_filter(['period' => $this->filtersQuery()['period'] ?? null, 'provider' => $provider]);

        return $parent instanceof Group
            ? ['name' => "grp.financials.bank_incoming.$section", 'parameters' => array_filter(['org' => $organisation?->slug, ...$query])]
            : ['name' => "grp.org.accounting.bank_incoming.$section", 'parameters' => ['organisation' => $parent->slug, ...$query]];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $this->readFilters($organisation, $request);

        return $this->handle($organisation, collect([$organisation]), $this->period);
    }

    /**
     * @return array<string, mixed>
     */
    public function inGroup(ActionRequest $request): array
    {
        $slug                     = $request->query('org');
        $this->groupOrganisations = self::accountingOrganisations(group(), $request->user());
        $this->forGroup(group(), $request->user(), is_string($slug) ? $slug : null);
        $this->initialisationFromGroup(group(), $request);
        $this->readFilters(group(), $request);

        return $this->handle(group(), $this->groupOrganisations->whereIn('id', $this->organisationIds)->values(), $this->period);
    }

    public function htmlResponse(array $payouts, ActionRequest $request): Response
    {
        $isGroup = $this->groupOrganisations !== null;
        $parent  = $isGroup ? $this->group : $this->organisation;

        return Inertia::render(
            'Org/Accounting/Payouts',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($isGroup, $request->route()->originalParameters()),
                'title'         => __('Payouts'),
                'pageHead'      => [
                    'title' => __('Payouts'),
                    'icon'  => ['fal', 'fa-hand-holding-box'],
                ],
                'organisation'  => $this->organisationSlug,
                'organisations' => $isGroup ? $this->groupOrganisations->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->values()->all() : [],
                'isGroup'       => $isGroup,
                'currency'      => $parent->currency->code,
                'payouts'       => $payouts,
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
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.payouts.index'], 'label' => __('Payouts')]],
                ]
            );
        }

        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.org.accounting.payouts.index', 'parameters' => $routeParameters], 'label' => __('Payouts')]],
            ]
        );
    }
}
