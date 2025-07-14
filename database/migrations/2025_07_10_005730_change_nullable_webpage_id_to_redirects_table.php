<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Jul 2025 12:24:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->unsignedInteger('to_webpage_id')->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->unsignedInteger('to_webpage_id')->nullable(false)->change();
        });
    }
};
