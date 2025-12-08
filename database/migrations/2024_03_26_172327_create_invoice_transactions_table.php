<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:24:42 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('invoice_transactions', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->dateTimeTz('date')->index();

            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('invoice_id')->nullable();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->string('model_type')->index()->nullable();
            $table->unsignedInteger('model_id')->index()->nullable();

            $table->unsignedInteger('asset_id')->index()->nullable();
            $table->foreign('asset_id')->references('id')->on('assets');
            $table->unsignedInteger('historic_asset_id')->index()->nullable();
            $table->foreign('historic_asset_id')->references('id')->on('historic_assets');

            $table->unsignedInteger('family_id')->nullable();
            $table->foreign('family_id')->references('id')->on('product_categories');
            $table->unsignedInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('product_categories');

            $table->unsignedInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->unsignedBigInteger('recurring_bill_transaction_id')->nullable();

            $table->decimal('quantity', 16, 3);

            $table->decimal('gross_amount', 16)->default(0);
            $table->decimal('net_amount', 16)->default(0);
            $table->decimal('profit_amount', 16)->nullable();

            $table->unsignedSmallInteger('tax_category_id')->index();
            $table->foreign('tax_category_id')->references('id')->on('tax_categories');
            $table->decimal('grp_exchange', 16, 4)->nullable();
            $table->decimal('org_exchange', 16, 4)->nullable();
            $table->decimal('grp_net_amount', 16)->nullable();
            $table->decimal('org_net_amount', 16)->nullable();

            $table->jsonb('data');

            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();

            $table->string('source_id')->nullable()->index();
            $table->string('source_alt_id')->nullable()->comment('to be used in no products transactions');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transactions');
    }
};
