<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sales_target_tips', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->unsignedSmallInteger('invoice_category_id')->nullable();
            $table->foreign('invoice_category_id')->references('id')->on('invoice_categories')->cascadeOnDelete();
            $table->date('date');
            $table->text('tip');
            $table->jsonb('facts');
            $table->timestampsTz();
            $table->unique(['shop_id', 'date', 'invoice_category_id'])->nullsNotDistinct();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_target_tips');
    }
};
