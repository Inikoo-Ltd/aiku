<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sept 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('model_has_media', function (Blueprint $table) {
            $table->boolean('is_caption_reviewed')->default(false);
            $table->text('source_caption')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('model_has_media', function (Blueprint $table) {
            $table->dropColumn(['is_caption_reviewed', 'source_caption']);
        });
    }
};
