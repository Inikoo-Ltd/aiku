<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Create return_items table for individual items within a return
 */

use App\Enums\GoodsIn\Return\ReturnItemStateEnum;
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

            $table->unsignedBigInteger('delivery_note_item_id')->index()->nullable();
            $table->foreign('delivery_note_item_id')->references('id')->on('delivery_note_items')->nullOnDelete();

            $table->string('notes')->nullable();
            $table->string('state')->default(ReturnItemStateEnum::WAITING_TO_RECEIVE->value)->index();

            $table->decimal('weight', 16, 3)->nullable();
            $table->decimal('quantity_expected', 16, 3)->default(0)->comment('Expected quantity to be returned');
            $table->decimal('quantity_received', 16, 3)->nullable()->comment('Actual quantity received');
            $table->decimal('quantity_accepted', 16, 3)->nullable()->comment('Quantity accepted after inspection');
            $table->decimal('quantity_rejected', 16, 3)->nullable()->comment('Quantity rejected after inspection');
            $table->decimal('quantity_restocked', 16, 3)->nullable()->comment('Quantity restocked to inventory');

            $table->decimal('revenue_amount', 16)->default(0);
            $table->decimal('org_revenue_amount', 16)->default(0);
            $table->decimal('grp_revenue_amount', 16)->default(0);

            $table->decimal('refund_amount', 16)->nullable();
            $table->decimal('org_refund_amount', 16)->nullable();
            $table->decimal('grp_refund_amount', 16)->nullable();

            $table->dateTimeTz('date')->nullable();
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('inspecting_at')->nullable();
            $table->dateTimeTz('accepted_at')->nullable();
            $table->dateTimeTz('rejected_at')->nullable();
            $table->dateTimeTz('restocked_at')->nullable();

            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->foreign('order_id')->references('id')->on('orders');
            $table->unsignedInteger('invoice_id')->nullable()->index();
            $table->foreign('invoice_id')->references('id')->on('invoices');

            $table->integer('estimated_weight')->default(0)->comment('grams');
            $table->text('rejection_reason')->nullable();
            $table->string('condition')->nullable()->comment('Item condition: new, used, damaged, etc.');

            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
