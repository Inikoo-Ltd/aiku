<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('preferred_shippings', function (Blueprint $table) {
            $table->unsignedInteger('min_weight')->nullable()->comment('grams, the rule applies from this weight');
            $table->unsignedInteger('max_weight')->nullable()->comment('grams, the rule applies below this weight');
        });
    }

    public function down(): void
    {
        Schema::table('preferred_shippings', function (Blueprint $table) {
            $table->dropColumn(['min_weight', 'max_weight']);
        });
    }
};
