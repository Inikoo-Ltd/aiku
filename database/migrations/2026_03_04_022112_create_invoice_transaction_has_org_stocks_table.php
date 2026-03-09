<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
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
        Schema::create('invoice_transaction_has_org_stocks', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedBigInteger('invoice_transaction_id');
            $table->foreign('invoice_transaction_id')->references('id')->on('invoice_transactions')->cascadeOnDelete();

            $table->unsignedInteger('org_stock_id');
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->cascadeOnDelete();

            $table->unsignedInteger('org_stock_family_id')->nullable()->index();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->nullOnDelete();

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
            $table->boolean('is_refund')->default(false)->index();

            $table->unique(['invoice_transaction_id', 'org_stock_id'], 'invoice_transaction_org_stock_unique');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transaction_has_org_stocks');
    }
};
