<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Jul 2025 11:50:30 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('debug_webhooks', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->jsonb('data');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('debug_webhooks');
    }
};
