<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 14:35:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tiktok_user_has_orders', function (Blueprint $table) {
            $table->renameColumn('orderable_id', 'model_id');
            $table->renameColumn('orderable_type', 'model_type');
        });
    }


    public function down(): void
    {
        Schema::table('tiktok_user_has_orders', function (Blueprint $table) {
            $table->renameColumn('model_id', 'orderable_id');
            $table->renameColumn('model_type', 'orderable_type');
        });
    }
};
