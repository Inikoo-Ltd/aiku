<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->boolean('blocks_later_steps')->default(true)->comment('false: the steps after this one can start before it is done, e.g. a second mix made while the first is already being made into product');
        });
    }

    public function down(): void
    {
        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->dropColumn('blocks_later_steps');
        });
    }
};
