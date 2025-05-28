<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 May 2025 10:10:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->change();
        });
    }


    public function down()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable(false)->change();
        });
    }
};
