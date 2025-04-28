<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 13:52:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->string('sales_type')->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn('sales_type');
        });
    }
};
