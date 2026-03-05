<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Mar 2026 23:06:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shippers', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'code']);
        });
    }


    public function down(): void
    {
        Schema::table('shippers', function (Blueprint $table) {
            $table->unique(['group_id', 'code']);
        });
    }
};
