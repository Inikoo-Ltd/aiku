<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 14:09:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_items_state_picked')->default(0);
            $table->unsignedSmallInteger('number_items_state_packing')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn(['number_items_state_picked', 'number_items_state_packing']);
        });
    }
};
