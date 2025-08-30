<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Aug 2025 09:50:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            if (Schema::hasColumn('webpages', 'is_use_canonical_url')) {
                $table->dropColumn('is_use_canonical_url');
            }
        });
    }


    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            if (!Schema::hasColumn('webpages', 'is_use_canonical_url')) {
                $table->boolean('is_use_canonical_url')->default(false);
            }
        });
    }
};
