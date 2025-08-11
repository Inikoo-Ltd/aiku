<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Aug 2025 07:47:47 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->string('description_title')->nullable();
            $table->text('description_extra')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn(['description_title', 'description_extra']);
        });
    }
};
