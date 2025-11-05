<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Oct 2025 12:21:56 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Rename JSON column allowances -> trigger_data
            if (Schema::hasColumn('offers', 'allowances')) {
                $table->renameColumn('allowances', 'trigger_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'trigger_data')) {
                $table->renameColumn('trigger_data', 'allowances');
            }
        });
    }
};
