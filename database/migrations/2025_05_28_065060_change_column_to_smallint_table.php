<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 May 2025 10:13:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedSmallInteger('organisation_id')->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedSmallInteger('organisation_id')->nullable(false)->change();
        });
    }
};
