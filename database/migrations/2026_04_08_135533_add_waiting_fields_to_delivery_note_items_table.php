<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Apr 2026 21:57:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->decimal('quantity_waiting_warehouse', 16, 6)->default(0);
            $table->decimal('quantity_waiting_crm', 16, 6)->default(0);
            $table->boolean('has_waiting_warehouse')->default(false);
            $table->boolean('has_waiting_crm')->default(false);
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_items_waiting_warehouse')->default(0);
            $table->unsignedSmallInteger('number_items_waiting_crm')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn([
                'quantity_waiting_warehouse',
                'quantity_waiting_crm',
                'has_waiting_warehouse',
                'has_waiting_crm',
            ]);
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn([
                'number_items_waiting_warehouse',
                'number_items_waiting_crm',
            ]);
        });
    }
};
