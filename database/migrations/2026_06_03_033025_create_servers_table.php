<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jun 2026 10:47:47 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug')->unique()->index();
            $table->boolean('active')->default(true);
            $table->string('name')->index();
            $table->string('ip');
            $table->boolean('master')->default(false);
            $table->boolean('slave')->default(false);
            $table->json('data')->nullable();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
