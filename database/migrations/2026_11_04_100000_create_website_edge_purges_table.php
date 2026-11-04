<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_edge_purges', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->string('trigger')->comment('scheduled: booked by a ban, 10 min debounce; manual: Break cache button or command; deploy: Varnish flush on deploy');
            $table->unsignedInteger('bans')->default(0)->comment('Bans that asked for an edge purge since the previous purge of this website');
            $table->jsonb('ban_sources')->default('{}')->comment('Ban count by the chain of aiku classes that called it, outermost first');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['website_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_edge_purges');
    }
};
