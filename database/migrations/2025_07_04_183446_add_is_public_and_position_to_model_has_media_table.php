<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 19:39:03 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('model_has_media', function (Blueprint $table) {
            $table->boolean('is_public')->index()->default(false);
            $table->unsignedSmallInteger('position')->index()->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('model_has_media', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'position']);
        });
    }
};
