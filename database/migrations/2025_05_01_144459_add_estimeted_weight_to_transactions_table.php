<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 May 2025 22:45:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('estimated_weight')->nullable()->comment('grams');
        });
    }


    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('estimated_weight');
        });
    }
};
