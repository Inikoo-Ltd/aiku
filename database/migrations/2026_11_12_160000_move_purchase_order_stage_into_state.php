<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const array NEW_STATES = ['in_production', 'in_warehouse', 'ready', 'handed_over', 'arrived'];

    private const array STATS_TABLES = [
        'agent_stats',
        'group_procurement_stats',
        'historic_supplier_product_stats',
        'org_agent_stats',
        'org_partner_stats',
        'org_stock_stats',
        'org_supplier_product_stats',
        'org_supplier_stats',
        'organisation_procurement_stats',
        'stock_stats',
        'supplier_product_stats',
        'supplier_stats',
    ];

    private const array COUNTED_BY = [
        'agent_stats'                    => ['agent_id', 'po.agent_id', ''],
        'supplier_stats'                 => ['supplier_id', 'po.supplier_id', ''],
        'org_agent_stats'                => ['org_agent_id', 'org_agents.id', 'join org_agents on org_agents.agent_id = po.agent_id and org_agents.organisation_id = po.organisation_id'],
        'org_supplier_stats'             => ['org_supplier_id', 'po.parent_id', "and po.parent_type = 'OrgSupplier'"],
        'org_partner_stats'              => ['org_partner_id', 'po.parent_id', "and po.parent_type = 'OrgPartner'"],
        'organisation_procurement_stats' => ['organisation_id', 'po.organisation_id', ''],
        'group_procurement_stats'        => ['group_id', 'po.group_id', ''],
    ];

    public function up(): void
    {
        foreach (self::STATS_TABLES as $statsTable) {
            Schema::table($statsTable, function (Blueprint $table) use ($statsTable) {
                foreach (self::NEW_STATES as $state) {
                    if (!Schema::hasColumn($statsTable, 'number_purchase_orders_state_'.$state)) {
                        $table->unsignedInteger('number_purchase_orders_state_'.$state)->default(0);
                    }
                }
            });
        }

        DB::statement("update purchase_orders
            set state = case when stage = 'received' then 'arrived' else stage end
            where state = 'confirmed' and stage in ('in_production', 'in_warehouse', 'ready', 'handed_over', 'arrived', 'received')");

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('stage');
        });

        $agreed  = ['confirmed', ...self::NEW_STATES];
        $counts  = implode(', ', array_map(fn (string $state) => "count(*) filter (where po.state = '$state') as $state", $agreed));
        $columns = implode(', ', array_map(fn (string $state) => "number_purchase_orders_state_$state = counted.$state", $agreed));

        foreach (self::COUNTED_BY as $statsTable => [$key, $ownerOfOrder, $scope]) {
            $join  = str_starts_with($scope, 'join') ? $scope : '';
            $where = str_starts_with($scope, 'and') ? $scope : '';

            DB::statement("update $statsTable
                set $columns
                from (
                    select $ownerOfOrder as owner_id, $counts
                    from purchase_orders po $join
                    where po.deleted_at is null and po.state in ('".implode("', '", $agreed)."') $where
                    group by $ownerOfOrder
                ) counted
                where $statsTable.$key = counted.owner_id");
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('stage')->nullable()->index();
        });

        DB::statement("update purchase_orders set stage = case state when 'confirmed' then 'deposit_paid' when 'settled' then 'received' else state end
            where state not in ('in_process', 'cancelled', 'not_received')");
        DB::statement("update purchase_orders set state = 'confirmed' where state in ('".implode("', '", self::NEW_STATES)."')");

        foreach (self::STATS_TABLES as $statsTable) {
            Schema::table($statsTable, function (Blueprint $table) {
                $table->dropColumn(array_map(fn (string $state) => 'number_purchase_orders_state_'.$state, self::NEW_STATES));
            });
        }
    }
};
