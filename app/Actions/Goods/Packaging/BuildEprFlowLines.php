<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Packaging;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rebuilds an organisation's packaging EPR flow lines for a date range from the documents that moved the goods:
 * received stock deliveries (imported, bought domestically, or packed and filled when they come from production)
 * dispatched delivery notes (sold domestically or exported) and the consumption of SKOs marked as shipment packaging
 * (cartons, mailers, tape used to send parcels), each line split into the trade units of its SKO with the packaging
 * family they carry. Returns read these lines, never the transactional tables.
 */
class BuildEprFlowLines
{
    use AsAction;

    public string $commandSignature = 'epr:build-flow-lines {organisations?* : organisation slugs, every shop organisation when empty} {--from= : first day, default the start of last month} {--to= : last day, default today}';

    /**
     * @return array{purchases: int, sales: int, shipment_packaging: int}
     */
    public function handle(Organisation $organisation, Carbon $from, Carbon $to): array
    {
        $bindings = ['organisation_id' => $organisation->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()];

        return DB::transaction(function () use ($bindings) {
            DB::delete('DELETE FROM epr_flow_lines WHERE organisation_id = :organisation_id AND date BETWEEN :from AND :to', $bindings);

            return [
                'purchases'          => DB::affectingStatement($this->purchasesSql(), $bindings),
                'sales'              => DB::affectingStatement($this->salesSql(), $bindings),
                'shipment_packaging' => DB::affectingStatement($this->shipmentPackagingSql(), $bindings),
            ];
        });
    }

    private function purchasesSql(): string
    {
        return <<<'SQL'
            INSERT INTO epr_flow_lines (group_id, organisation_id, date, activity, source_type, source_id, counterparty_country_id, org_stock_id, trade_unit_id, packaging_family_id, sko_quantity, quantity, created_at)
            SELECT sdi.group_id, sd.organisation_id, flow.date,
                CASE
                    WHEN sd.parent_type = 'Production' THEN 'packed_filled'
                    WHEN flow.country_id IS NULL THEN 'purchase_unknown_origin'
                    WHEN flow.country_id = organisation.country_id THEN 'bought_domestic'
                    ELSE 'imported'
                END,
                'StockDeliveryItem', sdi.id, flow.country_id, sdi.org_stock_id, trade_unit.trade_unit_id, trade_unit.packaging_family_id,
                flow.skos, flow.skos * COALESCE(trade_unit.quantity, 1), NOW()
            FROM stock_delivery_items sdi
            JOIN stock_deliveries sd ON sd.id = sdi.stock_delivery_id
            JOIN organisations organisation ON organisation.id = sd.organisation_id
            LEFT JOIN org_stocks os ON os.id = sdi.org_stock_id
            LEFT JOIN suppliers supplier ON supplier.id = sd.supplier_id
            LEFT JOIN addresses supplier_address ON supplier_address.id = supplier.address_id
            LEFT JOIN agents agent ON agent.id = sd.agent_id
            LEFT JOIN organisations agent_organisation ON agent_organisation.id = agent.organisation_id
            LEFT JOIN organisations partner ON partner.id = sd.partner_id
            LEFT JOIN LATERAL (
                SELECT mhtu.trade_unit_id, mhtu.quantity, tu.packaging_family_id
                FROM model_has_trade_units mhtu
                JOIN trade_units tu ON tu.id = mhtu.trade_unit_id
                WHERE mhtu.model_type = 'OrgStock' AND mhtu.model_id = sdi.org_stock_id
            ) trade_unit ON TRUE
            CROSS JOIN LATERAL (
                SELECT COALESCE(sd.received_at, sd.checked_at, sd.placed_at, sd.date)::date AS date,
                    CASE sd.parent_type
                        WHEN 'OrgSupplier' THEN supplier_address.country_id
                        WHEN 'OrgAgent' THEN agent_organisation.country_id
                        WHEN 'OrgPartner' THEN partner.country_id
                        WHEN 'Production' THEN organisation.country_id
                    END AS country_id,
                    CASE WHEN sdi.state IN ('checked', 'placed') THEN sdi.unit_quantity_checked ELSE sdi.unit_quantity END / GREATEST(COALESCE(os.packed_in, 1), 1) AS skos
            ) flow
            WHERE sd.organisation_id = :organisation_id
              AND sd.deleted_at IS NULL AND sdi.deleted_at IS NULL
              AND sd.state IN ('received', 'checked', 'booking_in', 'booked_in', 'placed')
              AND sdi.state NOT IN ('cancelled', 'not_received')
              AND flow.date BETWEEN :from AND :to
              AND flow.skos > 0
            SQL;
    }

    private function salesSql(): string
    {
        return <<<'SQL'
            INSERT INTO epr_flow_lines (group_id, organisation_id, date, activity, source_type, source_id, counterparty_country_id, org_stock_id, trade_unit_id, packaging_family_id, sko_quantity, quantity, shop_type, platform_id, created_at)
            SELECT dni.group_id, dn.organisation_id, dn.dispatched_at::date,
                CASE
                    WHEN dn.delivery_country_id IS NULL THEN 'sale_unknown_country'
                    WHEN dn.delivery_country_id = organisation.country_id THEN 'sold_domestic'
                    ELSE 'exported'
                END,
                'DeliveryNoteItem', dni.id, dn.delivery_country_id, dni.org_stock_id, trade_unit.trade_unit_id, trade_unit.packaging_family_id,
                dni.quantity_dispatched, dni.quantity_dispatched * COALESCE(trade_unit.quantity, 1), dn.shop_type, dn.platform_id, NOW()
            FROM delivery_notes dn
            JOIN delivery_note_items dni ON dni.delivery_note_id = dn.id
            JOIN organisations organisation ON organisation.id = dn.organisation_id
            LEFT JOIN LATERAL (
                SELECT mhtu.trade_unit_id, mhtu.quantity, tu.packaging_family_id
                FROM model_has_trade_units mhtu
                JOIN trade_units tu ON tu.id = mhtu.trade_unit_id
                WHERE mhtu.model_type = 'OrgStock' AND mhtu.model_id = dni.org_stock_id
            ) trade_unit ON TRUE
            WHERE dn.warehouse_id IN (SELECT id FROM warehouses WHERE organisation_id = :organisation_id)
              AND dn.organisation_id = :organisation_id
              AND dn.deleted_at IS NULL
              AND dn.state = 'dispatched'
              AND dn.dispatched_at >= :from::date AND dn.dispatched_at < :to::date + 1
              AND dni.quantity_dispatched > 0
            SQL;
    }

    private function shipmentPackagingSql(): string
    {
        return <<<'SQL'
            INSERT INTO epr_flow_lines (group_id, organisation_id, date, activity, source_type, source_id, org_stock_id, trade_unit_id, packaging_family_id, sko_quantity, quantity, created_at)
            SELECT m.group_id, m.organisation_id, m.date::date, 'shipment_packaging', 'OrgStockMovement', m.id, m.org_stock_id, trade_unit.trade_unit_id, trade_unit.packaging_family_id,
                -m.quantity, -m.quantity * COALESCE(trade_unit.quantity, 1), NOW()
            FROM org_stocks os
            JOIN org_stock_movements m ON m.org_stock_id = os.id
            LEFT JOIN LATERAL (
                SELECT mhtu.trade_unit_id, mhtu.quantity, tu.packaging_family_id
                FROM model_has_trade_units mhtu
                JOIN trade_units tu ON tu.id = mhtu.trade_unit_id
                WHERE mhtu.model_type = 'OrgStock' AND mhtu.model_id = os.id
            ) trade_unit ON TRUE
            WHERE os.organisation_id = :organisation_id
              AND os.is_shipment_packaging
              AND m.type IN ('consumption', 'return-consumption')
              AND m.date >= :from::date AND m.date < :to::date + 1
            SQL;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $from = $command->option('from') ? Carbon::parse($command->option('from')) : now()->subMonthNoOverflow()->startOfMonth();
        $to   = $command->option('to') ? Carbon::parse($command->option('to')) : now();

        $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP)
            ->when($command->argument('organisations'), fn ($query, $slugs) => $query->whereIn('slug', $slugs))
            ->get();

        foreach ($organisations as $organisation) {
            $built = $this->handle($organisation, $from, $to);
            $command->info("$organisation->slug {$from->toDateString()}..{$to->toDateString()}: {$built['purchases']} purchase lines, {$built['sales']} sale lines, {$built['shipment_packaging']} shipment packaging lines");
        }

        return 0;
    }
}
