<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Dec 2025 12:13:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Dispatching\WaitingItem\WaitingItemStateEnum;
use App\Enums\Dispatching\WaitingItem\WaitingItemStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('waiting_items', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();

            $table->unsignedInteger('order_id')->index();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->unsignedInteger('delivery_note_id')->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->nullOnDelete();


            $table->unsignedBigInteger('transaction_id')->index();
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();

            $table->unsignedBigInteger('delivery_note_item_id')->index();
            $table->foreign('delivery_note_item_id')->references('id')->on('delivery_note_items')->nullOnDelete();

            $table->string('type')->index();
            $table->string('state')->default(WaitingItemStateEnum::TO_DO->value)->index();
            $table->string('status')->default(WaitingItemStatusEnum::TO_DO->value)->index();

            $table->dateTimeTz('state_to_do_at')->nullable();
            $table->dateTimeTz('state_escalated_at')->nullable();
            $table->dateTimeTz('state_in_progress_at')->nullable();
            $table->dateTimeTz('state_done_at')->nullable();
            $table->dateTimeTz('state_cancelled_at')->nullable();

            $table->dateTimeTz('status_to_do_at')->nullable();
            $table->dateTimeTz('status_in_progress_at')->nullable();
            $table->dateTimeTz('status_done_at')->nullable();

            $table->unsignedInteger('reporter_user_id')->nullable()->index();
            $table->foreign('reporter_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('assignee_user_id')->nullable()->index();
            $table->foreign('assignee_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unsignedInteger('product_id')->nullable()->index();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->unsignedInteger('org_stock_id')->nullable()->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->nullOnDelete();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();

            $table->jsonb('data');

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('waiting_items');
    }
};
