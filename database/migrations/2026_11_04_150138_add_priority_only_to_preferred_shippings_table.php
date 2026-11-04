<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('preferred_shippings', function (Blueprint $table) {
            $table->boolean('priority_only')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('preferred_shippings', function (Blueprint $table) {
            $table->dropColumn('priority_only');
        });
    }
};
