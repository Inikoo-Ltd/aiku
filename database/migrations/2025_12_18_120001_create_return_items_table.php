<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 12:00:00 Makassar Time.
 * Description: Migration to create return_items table for items in a return
 */

use App\Enums\Dispatching\Return\ReturnItemStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedInteger('return_id')->index();
            $table->foreign('return_id')->references('id')->on('returns')->cascadeOnDelete();

            // Link to original delivery note item
            $table->unsignedInteger('delivery_note_item_id')->nullable()->index();
            $table->foreign('delivery_note_item_id')->references('id')->on('delivery_note_items')->nullOnDelete();

            // Link to original transaction
            $table->unsignedBigInteger('transaction_id')->index()->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();

            // Stock references
            $table->unsignedInteger('stock_family_id')->index()->nullable();
            $table->foreign('stock_family_id')->references('id')->on('stock_families');

            $table->unsignedInteger('stock_id')->index()->nullable();
            $table->foreign('stock_id')->references('id')->on('stocks')->nullOnUpdate();

            $table->unsignedInteger('org_stock_family_id')->index()->nullable();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->nullOnUpdate();

            $table->unsignedInteger('org_stock_id')->index()->nullable();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->nullOnUpdate();

            // State
            $table->string('state')->default(ReturnItemStateEnum::PENDING->value)->index()->comment('pending, received, inspected, restocked, rejected');

            // Reason for return of this item
            $table->string('return_reason')->nullable()->comment('damaged, wrong_item, quality_issue, change_of_mind, etc');
            $table->string('notes')->nullable();

            // Quantities
            $table->decimal('quantity_ordered', 16, 3)->nullable()->comment('Original quantity ordered');
            $table->decimal('quantity_dispatched', 16, 3)->nullable()->comment('Quantity that was dispatched');
            $table->decimal('quantity_returned', 16, 3)->default(0)->comment('Quantity being returned');
            $table->decimal('quantity_received', 16, 3)->nullable()->comment('Quantity actually received back');
            $table->decimal('quantity_restocked', 16, 3)->nullable()->comment('Quantity put back to stock');
            $table->decimal('quantity_rejected', 16, 3)->nullable()->comment('Quantity rejected (damaged/unusable)');

            // Weight
            $table->decimal('weight', 16, 3)->nullable();

            // Financial
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0)->comment('quantity_returned * unit_price');
            $table->decimal('refund_amount', 16, 2)->default(0);

            // Timestamps for state tracking
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('inspected_at')->nullable();
            $table->dateTimeTz('restocked_at')->nullable();
            $table->dateTimeTz('rejected_at')->nullable();

            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();

            $table->index(['return_id', 'state']);
            $table->index(['org_stock_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
