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
        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->string('customs_mrn')->nullable()->index();
            $table->date('customs_released_at')->nullable();
        });

        Schema::create('stock_delivery_customs_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries')->cascadeOnDelete();
            $table->string('tariff_code')->index();
            $table->string('description')->nullable();
            $table->decimal('duty_rate', 7, 4)->default(0);
            $table->decimal('customs_value', 16)->default(0);
            $table->decimal('duty_amount', 16)->default(0);
            $table->decimal('import_vat', 16)->nullable();
            $table->timestampsTz();
        });

        Schema::table('stock_delivery_items', function (Blueprint $table) {
            $table->unsignedInteger('stock_delivery_customs_line_id')->nullable()->index();
            $table->foreign('stock_delivery_customs_line_id')->references('id')->on('stock_delivery_customs_lines')->nullOnDelete();
            $table->string('discrepancy_outcome')->nullable()->index();
            $table->dateTimeTz('discrepancy_resolved_at')->nullable();
            $table->unsignedSmallInteger('discrepancy_resolved_by_id')->nullable();
            $table->foreign('discrepancy_resolved_by_id')->references('id')->on('users')->nullOnDelete();
            $table->dropColumn('net_unit_price');
        });

        Schema::create('stock_delivery_claims', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');
            $table->unsignedBigInteger('stock_delivery_item_id')->unique();
            $table->foreign('stock_delivery_item_id')->references('id')->on('stock_delivery_items');
            $table->unsignedInteger('org_stock_id')->nullable()->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');
            $table->string('state')->index();
            $table->decimal('quantity', 16, 4);
            $table->decimal('amount', 16);
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->string('credit_note_reference')->nullable()->index();
            $table->decimal('credit_note_amount', 16)->nullable();
            $table->date('credit_note_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('created_by_id')->nullable();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTimeTz('sent_at')->nullable();
            $table->dateTimeTz('closed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_stock_delivery_items_possible_unit_mismatch')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->dropColumn(['number_stock_delivery_items_possible_unit_mismatch', 'customs_mrn', 'customs_released_at']);
        });

        Schema::dropIfExists('stock_delivery_claims');

        Schema::table('stock_delivery_items', function (Blueprint $table) {
            $table->dropForeign(['stock_delivery_customs_line_id']);
            $table->dropForeign(['discrepancy_resolved_by_id']);
            $table->dropColumn(['stock_delivery_customs_line_id', 'discrepancy_outcome', 'discrepancy_resolved_at', 'discrepancy_resolved_by_id']);
            $table->decimal('net_unit_price', 16, 4)->default(0);
        });

        Schema::dropIfExists('stock_delivery_customs_lines');
    }
};
