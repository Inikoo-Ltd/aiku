<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedSmallInteger('order_from_month')->nullable()->comment('seasonal tag: first month (1-12) its products are suggested for ordering');
            $table->unsignedSmallInteger('order_to_month')->nullable()->comment('seasonal tag: last month (1-12) its products are suggested for ordering');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn(['order_from_month', 'order_to_month']);
        });
    }
};
