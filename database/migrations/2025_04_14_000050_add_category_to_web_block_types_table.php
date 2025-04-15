<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 14:33:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('web_block_types', function (Blueprint $table) {
            $table->string('category')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('web_block_types', function (Blueprint $table) {
            $table->dropColumn(['category']);
        });
    }
};
