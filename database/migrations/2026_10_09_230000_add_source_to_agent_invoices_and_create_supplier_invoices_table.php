<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('agent_invoices', function (Blueprint $table) {
            $table->string('source')->default('agent')->index();
            $table->jsonb('data')->default('{}');
            $table->unsignedInteger('number')->nullable()->change();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('supplier_id')->nullable()->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedInteger('stock_delivery_id')->unique();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');
            $table->string('source')->index();
            $table->string('reference')->nullable()->index();
            $table->date('date');
            $table->unsignedSmallInteger('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->unsignedInteger('number_lines')->default(0);
            $table->decimal('goods_amount', 16)->default(0);
            $table->decimal('charges_amount', 16)->default(0);
            $table->decimal('total_amount', 16)->default(0);
            $table->jsonb('lines');
            $table->jsonb('charges')->default('[]');
            $table->jsonb('data')->default('{}');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');

        Schema::table('agent_invoices', function (Blueprint $table) {
            $table->dropColumn(['source', 'data']);
            $table->unsignedInteger('number')->nullable(false)->change();
        });
    }
};
