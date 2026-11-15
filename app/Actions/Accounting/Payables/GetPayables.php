<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payables;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Models\Helpers\Currency;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the organisations still owe suppliers and agents, one row per open document, each worked out as the page of
 * that document works it out:
 * - a supplier's invoice (as received, never an estimate) less the payments recorded on its delivery and the deposits
 *   paid on its purchase orders, counted on the first delivery of each order only (ShowStockDelivery);
 * - an agent's invoice (never an estimate) less the deposits applied to its container and the transfers sent for it
 *   (IndexAgentInvoices);
 * - an agent's money request still open, less the transfers recorded against it (PayAgentMoneyRequest);
 * - a freight, customs or other service invoice of a delivery not marked paid.
 * Invoices of cancelled, not received or deleted deliveries are left out. No document carries a due date and the
 * agreed payment terms are free text, so due_date is empty for every kind until one of them records it.
 *
 * An agent run through the money ledger (its ledger for the organisation holds payments to suppliers; fees and
 * charges alone do not make it so) is owed its wallet deficit instead, one agent_wallets row per currency: what it spent less what it received, as IndexAgentLedger::wallets.
 * Rows listed but not owed carry counted false and are never added to a total: the invoices of such an agent (they
 * are in its wallet), money requests not approved yet, and not_invoiced, the estimated invoices of deliveries
 * received whose real invoice has not been entered.
 */
class GetPayables
{
    public const array KINDS = ['supplier_invoices', 'agent_wallets', 'agent_invoices', 'money_requests', 'service_invoices'];

    public const array RECEIVED_STATES = [StockDeliveryStateEnum::RECEIVED, StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::BOOKING_IN, StockDeliveryStateEnum::BOOKED_IN, StockDeliveryStateEnum::PLACED];

    public const array DUE_BUCKETS = ['overdue_90', 'overdue_61_90', 'overdue_31_60', 'overdue_1_30', 'due_7', 'due_30', 'later', 'none'];

    public const array AGE_BUCKETS = ['age_0_30', 'age_31_60', 'age_61_90', 'age_90'];

    /**
     * @param array<int, int> $organisationIds
     * @param int $currencyId the currency the amount column is converted to, at the last rate known today
     */
    public static function query(array $organisationIds, int $currencyId): Builder
    {
        $organisations = implode(',', array_map('intval', $organisationIds)) ?: '0';
        $pivot         = (int) Currency::where('code', config('app.currency_exchange.pivot'))->value('id');
        $rate          = fn (string $currency) => "case when $currency = $pivot then 1 else (select ce.exchange from currency_exchanges ce where ce.currency_id = $currency and ce.date <= current_date order by ce.date desc limit 1) end";
        $estimated     = StockDeliveryInvoiceSourceEnum::ESTIMATED->value;
        $deadStates    = "'".StockDeliveryStateEnum::CANCELLED->value."','".StockDeliveryStateEnum::NOT_RECEIVED->value."'";
        $transfer      = AgentPaymentTypeEnum::TRANSFER_RECEIVED->value;
        $requested     = DepositRequestStateEnum::REQUESTED->value;

        $moneyIn       = "'".implode("','", AgentPaymentTypeEnum::moneyInValues())."'";
        $supplierPayments = "'".implode("','", AgentPaymentTypeEnum::supplierPaymentValues())."'";
        $received      = "'".implode("','", array_map(fn (StockDeliveryStateEnum $state) => $state->value, self::RECEIVED_STATES))."'";
        $ledgerRun     = fn (string $alias) => "exists (select 1 from agent_payments spent where spent.agent_id = $alias.agent_id and spent.organisation_id = $alias.organisation_id and spent.deleted_at is null and spent.type in ($supplierPayments))";
        $orgAgentSlug  = fn (string $alias) => "(select oa.slug from org_agents oa where oa.agent_id = $alias.agent_id and oa.organisation_id = $alias.organisation_id limit 1)";

        $supplierInvoices = fn (string $kind, string $where, string $counted) => "
            select '$kind' as kind, si.id, si.organisation_id, 'supplier' as creditor_type, si.supplier_id as creditor_id, s.name as creditor_name,
                (select os.slug from org_suppliers os where os.supplier_id = si.supplier_id and os.organisation_id = si.organisation_id limit 1) as creditor_slug,
                si.reference, si.date, null::date as due_date, si.currency_id, si.total_amount as total,
                coalesce((select sum(sp.amount) from supplier_payments sp where sp.stock_delivery_id = si.stock_delivery_id and sp.deleted_at is null), 0)
                + coalesce((
                    select sum(coalesce(
                        (select sum(dp.amount) from purchase_order_deposit_payments dp where dp.purchase_order_id = po.id and dp.deleted_at is null),
                        case when po.deposit_paid_at is not null and po.deposit_amount > 0 then po.deposit_amount else 0 end
                    ))
                    from purchase_order_stock_delivery posd
                    join purchase_orders po on po.id = posd.purchase_order_id and po.deleted_at is null
                    where posd.stock_delivery_id = si.stock_delivery_id and po.currency_id = si.currency_id
                    and si.stock_delivery_id = (
                        select min(first.stock_delivery_id) from purchase_order_stock_delivery first
                        join stock_deliveries first_delivery on first_delivery.id = first.stock_delivery_id and first_delivery.deleted_at is null
                        where first.purchase_order_id = po.id
                    )
                ), 0) as paid,
                sd.slug as stock_delivery_slug, sd.reference as stock_delivery_reference, null::timestamptz as approved_at, $counted as counted
            from supplier_invoices si
            join stock_deliveries sd on sd.id = si.stock_delivery_id
            left join suppliers s on s.id = si.supplier_id
            where si.organisation_id in ($organisations) and $where
                and sd.deleted_at is null and sd.state not in ($deadStates) and sd.agent_id is null and sd.parent_type = 'OrgSupplier'";

        $agentInvoices = fn (string $kind, string $where, string $counted) => "
            select '$kind', ai.id, ai.organisation_id, 'agent', ai.agent_id, a.name, {$orgAgentSlug('ai')},
                ai.reference, ai.date, null::date, ai.currency_id, ai.total_amount,
                coalesce((select sum(da.amount) from stock_delivery_deposit_applications da where da.stock_delivery_id = ai.stock_delivery_id and da.deleted_at is null), 0)
                + coalesce((select sum(ap.amount) from agent_payments ap where ap.stock_delivery_id = ai.stock_delivery_id and ap.type = '$transfer' and ap.deleted_at is null), 0),
                sd.slug, sd.reference, null::timestamptz, $counted
            from agent_invoices ai
            join stock_deliveries sd on sd.id = ai.stock_delivery_id
            join agents a on a.id = ai.agent_id
            where ai.organisation_id in ($organisations) and $where and sd.deleted_at is null and sd.state not in ($deadStates)";

        $documents = "
            {$supplierInvoices('supplier_invoices', "si.source <> '$estimated'", 'true')}
            union all
            {$supplierInvoices('not_invoiced', "si.source = '$estimated' and sd.state in ($received)", 'false')}
            union all
            {$agentInvoices('agent_invoices', "ai.source <> '$estimated'", "not {$ledgerRun('ai')}")}
            union all
            {$agentInvoices('not_invoiced', "ai.source = '$estimated' and sd.state in ($received)", 'false')}
            union all
            select 'agent_wallets', min(ap.id), ap.organisation_id, 'agent', ap.agent_id, min(a.name), {$orgAgentSlug('ap')},
                null, coalesce(max(ap.date), max(ap.created_at)::date), null::date, ap.currency_id,
                sum(case when ap.type not in ($moneyIn) then ap.amount else 0 end),
                sum(case when ap.type in ($moneyIn) then ap.amount else 0 end),
                null, null, null::timestamptz, true
            from agent_payments ap
            join agents a on a.id = ap.agent_id
            where ap.organisation_id in ($organisations) and ap.deleted_at is null and {$ledgerRun('ap')}
            group by ap.organisation_id, ap.agent_id, ap.currency_id
            union all
            select 'money_requests', dr.id, dr.organisation_id, 'agent', dr.agent_id, a.name, {$orgAgentSlug('dr')},
                dr.reference, dr.requested_at::date, null::date, dr.currency_id,
                coalesce((select sum(dri.amount) from deposit_request_items dri where dri.deposit_request_id = dr.id), 0),
                coalesce((select sum(ap.amount) from agent_payments ap where ap.deposit_request_id = dr.id and ap.deleted_at is null), 0),
                null, null, dr.approved_at, dr.approved_at is not null
            from deposit_requests dr
            join agents a on a.id = dr.agent_id
            where dr.organisation_id in ($organisations) and dr.state = '$requested'
            union all
            select 'service_invoices', sv.id, sv.organisation_id, 'issuer', null::integer, sv.issuer, null,
                sv.reference, sv.date, null::date, sv.currency_id, sv.total_amount, 0, null, null, null::timestamptz, true
            from stock_delivery_service_invoices sv
            where sv.organisation_id in ($organisations) and sv.paid_at is null and sv.deleted_at is null";

        $owed = '(documents.total - documents.paid)';

        return DB::query()->fromSub(
            "select documents.*, $owed as owed, currencies.code as currency_code, organisations.code as organisation_code, organisations.slug as organisation_slug,
                documents.creditor_type || ':' || coalesce(documents.creditor_id::text, lower(trim(documents.creditor_name))) as creditor_key,
                case when documents.currency_id = $currencyId then $owed else round($owed * ({$rate((string) $currencyId)}) / nullif(({$rate('documents.currency_id')}), 0), 2) end as amount
            from ($documents) documents
            join currencies on currencies.id = documents.currency_id
            join organisations on organisations.id = documents.organisation_id
            where $owed > 0.005",
            'payables'
        );
    }

    /**
     * Where a due date falls from today: how long overdue, or how soon due; a document with no due date is in none.
     */
    public static function dueBucket(?string $dueDate, ?Carbon $today = null): string
    {
        if ($dueDate === null) {
            return 'none';
        }

        $days = (int) ($today ?? now())->copy()->startOfDay()->diffInDays(Carbon::parse($dueDate)->startOfDay(), false);

        return match (true) {
            $days < -90 => 'overdue_90',
            $days < -60 => 'overdue_61_90',
            $days < -30 => 'overdue_31_60',
            $days < 0   => 'overdue_1_30',
            $days <= 7  => 'due_7',
            $days <= 30 => 'due_30',
            default     => 'later',
        };
    }

    /**
     * How old a document is from today, by the date written on it.
     */
    public static function ageBucket(string $date, ?Carbon $today = null): string
    {
        $days = (int) Carbon::parse($date)->startOfDay()->diffInDays(($today ?? now())->copy()->startOfDay(), false);

        return match (true) {
            $days > 90 => 'age_90',
            $days > 60 => 'age_61_90',
            $days > 30 => 'age_31_60',
            default    => 'age_0_30',
        };
    }
}
