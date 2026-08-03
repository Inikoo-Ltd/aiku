<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Jul 2026 00:37:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('group_set_up_keys');
    }

    public function down(): void
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
};
