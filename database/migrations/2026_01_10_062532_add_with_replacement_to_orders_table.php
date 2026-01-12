<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Jan 2026 15:01:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('with_replacement')->default(false)->index();
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('with_replacement');
        });
    }
};
