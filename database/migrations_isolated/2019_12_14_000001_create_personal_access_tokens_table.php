<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Mar 2023 22:44:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });
    }


    public function down()
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
