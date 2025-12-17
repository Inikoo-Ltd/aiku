<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Dec 2025 23:00:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dateTimeTz('dispatched_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['dispatched_at', 'cancelled_at']);
        });
    }
};
