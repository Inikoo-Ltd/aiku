<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 31 Jul 2025 13:46:47 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('sort_code', 64)->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('sort_code');
        });
    }
};
