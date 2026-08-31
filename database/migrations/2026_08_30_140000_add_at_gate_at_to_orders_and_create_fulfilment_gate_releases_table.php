<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTimeTz('at_gate_at')->nullable()->index();
        });

        Schema::create('fulfilment_gate_releases', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->unsignedSmallInteger('warehouse_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->unsignedInteger('order_id')->index();
            $table->unsignedInteger('delivery_note_id')->nullable()->index();
            $table->decimal('net_amount', 16)->default(0);
            $table->unsignedInteger('number_items')->default(0);
            $table->unsignedInteger('seconds_since_last_release')->nullable();
            $table->unsignedInteger('released_by_user_id')->nullable();
            $table->timestampsTz();

            $table->foreign('group_id')->references('id')->on('groups');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->foreign('order_id')->references('id')->on('orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfilment_gate_releases');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('at_gate_at');
        });
    }
};
