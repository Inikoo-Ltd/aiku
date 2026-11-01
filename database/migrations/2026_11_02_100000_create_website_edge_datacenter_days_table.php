<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_edge_datacenter_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->date('date');
            $table->string('colo', 8)->comment('Cloudflare datacenter (IATA code) that answered');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('requests_hit')->default(0)->comment('Answered from that datacenter\'s edge copy');
            $table->unsignedInteger('requests_none')->default(0)->comment('Answered by Cloudflare rules: redirects, blocks');
            $table->timestampsTz();
            $table->unique(['website_id', 'date', 'colo']);
            $table->index(['date', 'colo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_edge_datacenter_days');
    }
};
