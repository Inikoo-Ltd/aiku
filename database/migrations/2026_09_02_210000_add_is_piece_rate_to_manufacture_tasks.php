<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_tasks', function (Blueprint $table) {
            $table->boolean('is_piece_rate')->default(true)->comment('false for salaried work such as preparing mixes: sessions are recorded but snapshot a zero rate');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_tasks', function (Blueprint $table) {
            $table->dropColumn('is_piece_rate');
        });
    }
};
