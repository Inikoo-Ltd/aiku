<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 16 May 2026 01:12:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->boolean('is_seeder')->default(false)->index();
        });
    }


    public function down(): void
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->dropColumn('is_seeder');
        });
    }
};
