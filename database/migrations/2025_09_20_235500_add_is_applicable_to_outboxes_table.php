<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Sept 2025 00:00:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('outboxes', function (Blueprint $table) {
            $table->boolean('is_applicable')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('outboxes', function (Blueprint $table) {
            $table->dropColumn('is_applicable');
        });
    }
};
