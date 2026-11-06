<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 18:33:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->unsignedInteger('image_id')->nullable();
            $table->jsonb('intel')->nullable();
            $table->timestampTz('intel_fetched_at')->nullable();
            $table->text('intel_error')->nullable();
        });

        Schema::table('competitor_products', function (Blueprint $table) {
            $table->decimal('was_price', 18, 4)->nullable();
        });

        Schema::create('competitor_daily_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('competitor_id');
            $table->foreign('competitor_id')->references('id')->on('competitors')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('number_products')->default(0);
            $table->unsignedInteger('number_on_sale')->default(0);
            $table->decimal('average_discount', 5, 2)->nullable();
            $table->timestampsTz();
            $table->unique(['competitor_id', 'date']);
        });

        Schema::create('competitor_intel_snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('competitor_id');
            $table->foreign('competitor_id')->references('id')->on('competitors')->cascadeOnDelete();
            $table->timestampTz('fetched_at')->index();
            $table->jsonb('intel');
            $table->jsonb('changes')->default('[]');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_intel_snapshots');
        Schema::dropIfExists('competitor_daily_stats');
        Schema::table('competitor_products', function (Blueprint $table) {
            $table->dropColumn('was_price');
        });
        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn(['image_id', 'intel', 'intel_fetched_at', 'intel_error']);
        });
    }
};
