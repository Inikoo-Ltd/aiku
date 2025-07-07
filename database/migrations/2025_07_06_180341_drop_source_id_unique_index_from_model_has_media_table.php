<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 19:04:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('model_has_media', function (Blueprint $table) {
            $table->dropUnique(['source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('model_has_media', function (Blueprint $table) {
            $table->unique(['source_id']);
        });
    }
};
