<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 21:54:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('pickings', function (Blueprint $table) {
            $table->decimal('quantity', 16, 6)->change();
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->decimal('original_quantity_required', 16, 6)->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('pickings', function (Blueprint $table) {
            $table->decimal('quantity', 16, 3)->change();
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->decimal('original_quantity_required', 16, 3)->change();
        });
    }
};
