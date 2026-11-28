<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cross_dispatch_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('ordering_organisation_id')->index();
            $table->foreign('ordering_organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('org_partner_id')->nullable()->index();
            $table->foreign('org_partner_id')->references('id')->on('org_partners');
            $table->unsignedInteger('delivery_note_id')->unique();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes');
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->foreign('order_id')->references('id')->on('orders');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('state')->index();
            $table->timestampTz('dispatched_at')->index();
            $table->jsonb('terms');
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->unsignedSmallInteger('number_parcels')->nullable();
            $table->decimal('dispatch_fee', 16, 2)->nullable();
            $table->decimal('shipping_amount', 16, 2)->nullable();
            $table->string('shipping_cost_source')->nullable();
            $table->decimal('goods_amount', 16, 2)->nullable();
            $table->jsonb('lines')->default('[]');
            $table->timestampTz('costed_at')->nullable()->index();
            $table->timestampTz('reversed_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_dispatch_charges');
    }
};
