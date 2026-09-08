<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('agent_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_agent_supplier_purchase_orders')->default(0);
        });

        Schema::table('org_agent_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_agent_supplier_purchase_orders')->default(0);
        });

        DB::statement("
            update agent_stats
            set number_agent_supplier_purchase_orders = counts.total
            from (
                select suppliers.agent_id, count(*) as total
                from agent_supplier_purchase_orders
                join suppliers on suppliers.id = agent_supplier_purchase_orders.supplier_id
                where agent_supplier_purchase_orders.deleted_at is null
                and suppliers.agent_id is not null
                group by suppliers.agent_id
            ) counts
            where agent_stats.agent_id = counts.agent_id
        ");

        DB::statement("
            update org_agent_stats
            set number_agent_supplier_purchase_orders = counts.total
            from (
                select purchase_orders.parent_id as org_agent_id, count(*) as total
                from agent_supplier_purchase_orders
                join purchase_orders on purchase_orders.id = agent_supplier_purchase_orders.purchase_order_id
                where agent_supplier_purchase_orders.deleted_at is null
                and purchase_orders.parent_type = 'OrgAgent'
                group by purchase_orders.parent_id
            ) counts
            where org_agent_stats.org_agent_id = counts.org_agent_id
        ");
    }

    public function down()
    {
        Schema::table('agent_stats', function (Blueprint $table) {
            $table->dropColumn('number_agent_supplier_purchase_orders');
        });

        Schema::table('org_agent_stats', function (Blueprint $table) {
            $table->dropColumn('number_agent_supplier_purchase_orders');
        });
    }
};
