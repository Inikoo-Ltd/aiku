<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 15:19:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('top_up_payment_api_points', function (Blueprint $table) {
            $table->decimal('amount', 12);
        });
    }


    public function down(): void
    {
        Schema::table('top_up_payment_api_points', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
