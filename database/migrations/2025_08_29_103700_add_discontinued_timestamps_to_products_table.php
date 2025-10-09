<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Aug 2025 21:22:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'mark_for_discontinued_at')) {
                $table->dateTimeTz('mark_for_discontinued_at')->nullable();
            }
            if (!Schema::hasColumn('products', 'discontinued_at')) {
                $table->dateTimeTz('discontinued_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'mark_for_discontinued_at')) {
                $table->dropColumn('mark_for_discontinued_at');
            }
            if (Schema::hasColumn('products', 'discontinued_at')) {
                $table->dropColumn('discontinued_at');
            }
        });
    }
};
