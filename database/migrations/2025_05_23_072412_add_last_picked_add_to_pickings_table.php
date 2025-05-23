<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 May 2025 16:09:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pickings', function (Blueprint $table) {
            $table->dateTimeTz('last_picked_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('pickings', function (Blueprint $table) {
            $table->dropColumn('last_picked_at');
        });
    }
};
