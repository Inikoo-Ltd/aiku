<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 May 2025 14:37:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->dropForeign(['image_id']);
            $table->dropColumn('image_id');
        });
    }
};
