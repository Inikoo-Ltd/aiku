<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jul 2024 15:25:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_set_up_keys', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->ulid('key');
            $table->string('state');
            $table->dateTimeTz('expires_at');
            $table->jsonb('limits');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_set_up_keys');
    }
};
