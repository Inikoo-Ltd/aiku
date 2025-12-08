<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Apr 2023 10:36:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariff_codes', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('tariff_codes');
        });
    }

    public function down(): void
    {
        Schema::table('tariff_codes', function (Blueprint $table) {
            $table->dropForeign('parent_id_foreign');
        });
    }
};
