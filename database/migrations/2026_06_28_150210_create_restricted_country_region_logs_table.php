<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Jun 2026 23:02:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('restricted_country_region_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_geolocation_id')->constrained('ip_geolocations')->cascadeOnDelete();
            $table->boolean('was_blocked')->index();
            $table->timestampsTz();
            $table->unique(['ip_geolocation_id', 'was_blocked']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('restricted_country_region_logs');
    }
};
