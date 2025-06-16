<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 May 2025 12:51:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('top_up_payment_api_points', function (Blueprint $table) {
            $table->boolean('in_process')->default(true)->index();
        });
    }


    public function down(): void
    {
        Schema::table('top_up_payment_api_points', function (Blueprint $table) {
            $table->dropColumn('in_process');
        });
    }
};
