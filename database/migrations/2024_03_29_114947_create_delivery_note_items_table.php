<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 01 Sept 2022 18:58:57 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('delivery_note_id')->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->cascadeOnDelete();

            $table->unsignedInteger('stock_family_id')->index()->nullable();
            $table->foreign('stock_family_id')->references('id')->on('stock_families');

            $table->unsignedInteger('stock_id')->index()->nullable();
            $table->foreign('stock_id')->references('id')->on('stocks')->nullOnUpdate();

            $table->unsignedInteger('org_stock_family_id')->index()->nullable();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->nullOnUpdate();

            $table->unsignedInteger('org_stock_id')->index()->nullable();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->nullOnUpdate();

            $table->unsignedBigInteger('transaction_id')->index()->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();

            $table->unsignedBigInteger('invoice_transaction_id')->index()->nullable();
            $table->foreign('invoice_transaction_id')->references('id')->on('invoice_transactions')->nullOnDelete();

            $table->string('notes')->nullable();

            $table->string('state')->default(DeliveryNoteItemStateEnum::QUEUED->value)->index();

            $table->decimal('weight', 16, 3)->nullable();

            $table->decimal('quantity_required', 16, 3)->default(0);
            $table->decimal('quantity_picked', 16, 3)->nullable();
            $table->decimal('quantity_packed', 16, 3)->nullable();
            $table->decimal('quantity_dispatched', 16, 3)->nullable();

            $table->decimal('revenue_amount', 16)->default(0);
            $table->decimal('org_revenue_amount', 16)->default(0);
            $table->decimal('grp_revenue_amount', 16)->default(0);

            $table->decimal('profit_amount', 16)->nullable();
            $table->decimal('org_profit_amount', 16)->nullable();
            $table->decimal('grp_profit_amount', 16)->nullable();

            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
