<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 27 Aug 2022 23:08:46 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasOrderFields;
use App\Stubs\Migrations\HasSalesTransactionParents;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasOrderFields;
    use HasSalesTransactionParents;

    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table = $this->salesTransactionParents($table);
            $table->unsignedInteger('invoice_id')->nullable()->index();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->datetimeTz('date');
            $table->dateTimeTz('submitted_at')->nullable();
            $table->dateTimeTz('in_warehouse_at')->nullable();

            $table->dateTimeTz('settled_at')->nullable();

            $table->string('state')->default()->index();
            $table->string('status')->default()->index();

            $table->string('model_type')->index()->nullable();
            $table->unsignedInteger('model_id')->index()->nullable();

            $table->unsignedInteger('asset_id')->index()->nullable();
            $table->foreign('asset_id')->references('id')->on('assets');
            $table->unsignedInteger('historic_asset_id')->index()->nullable();
            $table->foreign('historic_asset_id')->references('id')->on('historic_assets');

            $table->decimal('quantity_ordered', 16, 3)->nullable();
            $table->decimal('quantity_bonus', 16, 3)->default(0)->nullable();
            $table->decimal('quantity_dispatched', 16, 3)->default(0)->nullable();
            $table->decimal('quantity_fail', 16, 3)->default(0)->nullable();
            $table->decimal('quantity_cancelled', 16, 3)->default(0)->nullable();

            $table->boolean('out_of_stock_in_basket')->default(false)->index();
            $table->dateTimeTz('out_of_stock_in_basket_at')->nullable();

            $table->string('fail_status')->nullable()->index();

            $table = $this->orderMoneyFields($table);

            $table->jsonb('data');

            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
            $table->string('source_alt_id')->nullable()->unique()->comment('to be used in no products transactions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
