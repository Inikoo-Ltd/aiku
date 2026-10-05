<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('master_asset_price_tips', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('master_shop_id')->index();
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->cascadeOnDelete();
            $table->unsignedInteger('master_asset_id')->index();
            $table->foreign('master_asset_id')->references('id')->on('master_assets')->cascadeOnDelete();

            $table->smallInteger('change');
            $table->decimal('confidence', 5, 4);
            $table->jsonb('probabilities');
            $table->decimal('temporary_drop_probability', 5, 4)->nullable();
            $table->text('reason');
            $table->jsonb('state');
            $table->string('status')->index();
            $table->decimal('price', 18, 2);

            $table->text('dismissed_reason')->nullable();
            $table->unsignedSmallInteger('dismissed_by_user_id')->nullable();
            $table->foreign('dismissed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('dismissed_at')->nullable();

            $table->decimal('applied_price', 18, 2)->nullable();
            $table->unsignedSmallInteger('applied_by_user_id')->nullable();
            $table->foreign('applied_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('applied_at')->nullable()->index();

            $table->jsonb('outcome')->nullable();
            $table->timestampTz('measured_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampsTz();
            $table->index(['master_asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_asset_price_tips');
    }
};
