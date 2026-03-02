<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('invoice_transaction_has_trade_units', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedBigInteger('invoice_transaction_id');
            $table->foreign('invoice_transaction_id')->references('id')->on('invoice_transactions')->cascadeOnDelete();

            $table->unsignedInteger('trade_unit_id');
            $table->foreign('trade_unit_id')->references('id')->on('trade_units')->cascadeOnDelete();

            $table->unsignedSmallInteger('trade_unit_family_id')->nullable()->index();
            $table->foreign('trade_unit_family_id')->references('id')->on('trade_unit_families')->nullOnDelete();

            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->unsignedInteger('order_id')->nullable()->index();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->decimal('net_amount', 16)->default(0);
            $table->decimal('org_net_amount', 16)->default(0);
            $table->decimal('grp_net_amount', 16)->default(0);

            $table->string('type')->nullable()->index();
            $table->dateTimeTz('date')->index();
            $table->boolean('in_process')->default(false)->index();

            $table->unique(['invoice_transaction_id', 'trade_unit_id'], 'invoice_transaction_trade_unit_unique');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transaction_has_trade_units');
    }
};
