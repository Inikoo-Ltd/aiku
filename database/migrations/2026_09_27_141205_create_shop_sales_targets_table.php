<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shop_sales_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->date('month');
            $table->decimal('target_org_currency', 16, 2);
            $table->unsignedSmallInteger('set_by_user_id')->nullable();
            $table->foreign('set_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['shop_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_sales_targets');
    }
};
