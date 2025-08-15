<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Aug 2025 17:28:18 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('traffic_source_campaigns', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('traffic_source_id');
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('traffic_source_campaigns');
    }
};
