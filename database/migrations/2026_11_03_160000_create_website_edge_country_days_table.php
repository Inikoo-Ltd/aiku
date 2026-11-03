<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_edge_country_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->date('date');
            $table->string('country', 2)->comment('Visitor country (ISO alpha-2) as Cloudflare reads it');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('requests_hit')->default(0)->comment('Answered from the edge copy');
            $table->timestampsTz();
            $table->unique(['website_id', 'date', 'country']);
            $table->index(['date', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_edge_country_days');
    }
};
