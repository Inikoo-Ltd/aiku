<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:01 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_tasks', function (Blueprint $table) {
            $table->decimal('standard_rate', 10, 4)->nullable();
            $table->string('target_override_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_tasks', function (Blueprint $table) {
            $table->dropColumn(['standard_rate', 'target_override_reason']);
        });
    }
};
