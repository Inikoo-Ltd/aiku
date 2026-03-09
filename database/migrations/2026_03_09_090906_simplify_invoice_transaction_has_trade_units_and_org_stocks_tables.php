<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Mar 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('invoice_transaction_has_trade_units', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['organisation_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['order_id']);
            $table->dropColumn(['group_id', 'organisation_id', 'customer_id', 'order_id', 'type', 'date', 'in_process', 'is_refund']);
        });

        Schema::table('invoice_transaction_has_org_stocks', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['organisation_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['order_id']);
            $table->dropColumn(['group_id', 'organisation_id', 'customer_id', 'order_id', 'type', 'date', 'in_process', 'is_refund']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_transaction_has_trade_units', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_id')->index()->after('id');
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index()->after('group_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedInteger('customer_id')->nullable()->index()->after('trade_unit_family_id');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->unsignedInteger('order_id')->nullable()->index()->after('customer_id');
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->string('type')->nullable()->index()->after('grp_net_amount');
            $table->dateTimeTz('date')->index()->after('type');
            $table->boolean('in_process')->default(false)->index()->after('date');
            $table->boolean('is_refund')->default(false)->index()->after('in_process');
        });

        Schema::table('invoice_transaction_has_org_stocks', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_id')->index()->after('id');
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index()->after('group_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedInteger('customer_id')->nullable()->index()->after('org_stock_family_id');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->unsignedInteger('order_id')->nullable()->index()->after('customer_id');
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->string('type')->nullable()->index()->after('grp_net_amount');
            $table->dateTimeTz('date')->index()->after('type');
            $table->boolean('in_process')->default(false)->index()->after('date');
            $table->boolean('is_refund')->default(false)->index()->after('in_process');
        });
    }
};
