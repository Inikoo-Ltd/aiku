<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Aug 2025 04:35:29 Central Standard Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('snapshots', function (Blueprint $table) {
            $table->string('label')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
