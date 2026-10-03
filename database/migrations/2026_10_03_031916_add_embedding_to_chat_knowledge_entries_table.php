<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 11:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::table('chat_knowledge_entries', function (Blueprint $table) {
            $table->vector('embedding', dimensions: 1024)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_knowledge_entries', fn (Blueprint $table) => $table->dropColumn('embedding'));
    }
};
