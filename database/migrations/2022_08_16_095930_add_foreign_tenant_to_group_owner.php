<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:22:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('organisations');
        });
    }


    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign('owner_id_foreign');
        });
    }
};
