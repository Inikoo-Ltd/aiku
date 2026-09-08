<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 14:32:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('committers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('github_username')->nullable();
            $table->string('avatar')->nullable();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('committers');
    }
};
