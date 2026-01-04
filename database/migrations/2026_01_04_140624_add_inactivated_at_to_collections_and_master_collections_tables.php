<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 22:08:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dateTimeTz('inactivated_at')->nullable();
        });
        Schema::table('master_collections', function (Blueprint $table) {
            $table->dateTimeTz('inactivated_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('inactivated_at');
        });
        Schema::table('master_collections', function (Blueprint $table) {
            $table->dropColumn('inactivated_at');
        });
    }
};
