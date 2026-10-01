<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('pre_orders', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('order_id')->unique();
            $table->foreign('order_id')->references('id')->on('orders');
            $table->unsignedInteger('parent_order_id')->nullable()->index();
            $table->foreign('parent_order_id')->references('id')->on('orders');
            $table->string('state')->index();
            $table->boolean('is_trade')->default(true);
            $table->boolean('has_back_order')->default(false);
            $table->boolean('has_made_to_order')->default(false);
            $table->boolean('has_pallet_delivery')->default(false);
            $table->decimal('upfront_amount', 16, 2)->default(0);
            $table->decimal('deferred_amount', 16, 2)->default(0);
            $table->date('estimated_dispatch_from')->nullable();
            $table->date('estimated_dispatch_to')->nullable();
            $table->timestampTz('free_cancellation_until')->nullable();
            $table->timestampTz('supplier_ordered_at')->nullable();
            $table->timestampTz('goods_arrived_at')->nullable();
            $table->timestampTz('balance_requested_at')->nullable();
            $table->timestampTz('balance_due_at')->nullable();
            $table->timestampTz('balance_first_reminder_sent_at')->nullable();
            $table->timestampTz('balance_second_reminder_sent_at')->nullable();
            $table->timestampTz('balance_paid_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->decimal('pallet_estimate_amount', 16, 2)->nullable();
            $table->decimal('pallet_quote_amount', 16, 2)->nullable();
            $table->jsonb('terms');
            $table->jsonb('data');
            $table->timestampsTz();
        });

        Schema::table('org_stocks', function (Blueprint $table) {
            $table->decimal('quantity_reserved_for_pre_orders', 16, 3)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn('quantity_reserved_for_pre_orders');
        });
        Schema::dropIfExists('pre_orders');
    }
};
