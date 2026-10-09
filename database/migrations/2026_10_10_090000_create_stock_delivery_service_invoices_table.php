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
        Schema::create('stock_delivery_service_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->string('type')->index();
            $table->string('issuer')->index();
            $table->string('reference')->nullable()->index();
            $table->date('date')->index();
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->decimal('exchange', 16, 6)->default(1);
            $table->decimal('total_amount', 16);
            $table->decimal('org_total_amount', 16);
            $table->dateTimeTz('paid_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('stock_delivery_service_invoice_allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('stock_delivery_service_invoice_id')->index();
            $table->foreign('stock_delivery_service_invoice_id', 'sdsia_service_invoice_id_foreign')->references('id')->on('stock_delivery_service_invoices');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');
            $table->decimal('amount', 16);
            $table->timestampsTz();
            $table->unique(['stock_delivery_service_invoice_id', 'stock_delivery_id'], 'sdsia_service_invoice_stock_delivery_unique');
        });

        Schema::table('stock_delivery_costs', function (Blueprint $table) {
            $table->boolean('from_service_invoices')->default(false);
            $table->unsignedInteger('stock_delivery_service_invoice_id')->nullable()->index();
            $table->foreign('stock_delivery_service_invoice_id')->references('id')->on('stock_delivery_service_invoices');
        });
    }

    public function down(): void
    {
        Schema::table('stock_delivery_costs', function (Blueprint $table) {
            $table->dropForeign(['stock_delivery_service_invoice_id']);
            $table->dropColumn(['from_service_invoices', 'stock_delivery_service_invoice_id']);
        });
        Schema::dropIfExists('stock_delivery_service_invoice_allocations');
        Schema::dropIfExists('stock_delivery_service_invoices');
    }
};
