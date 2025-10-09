<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2025 09:47:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('shops', 'is_aiku')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->boolean('is_aiku')->index()->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shops', 'is_aiku')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('is_aiku');
            });
        }
    }
};
