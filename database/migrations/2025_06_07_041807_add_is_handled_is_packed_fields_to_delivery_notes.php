<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Jun 2025 12:18:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_items_handled')->default(0);
            $table->unsignedSmallInteger('number_items_need_packing')->default(0);
            $table->unsignedSmallInteger('number_items_packed')->default(0);
            $table->unsignedSmallInteger('number_items_done')->default(0);
            $table->boolean('is_picked')->default(false)->index();
            $table->boolean('is_packed')->default(false)->index();
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn('is_completed');
            $table->boolean('is_handled')->default(false)->index();
            $table->boolean('need_packing')->nullable()->index();
            $table->boolean('is_packed')->nullable()->index();
            $table->boolean('is_done')->default(false)->index();
        });
    }


    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn([
                'number_items_handled',
                'number_items_need_packing',
                'number_items_packed',
                'number_items_done',
                'is_picked',
                'is_packed'
            ]);
        });
    }
    public function downItems(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn([
                'is_handled',
                'need_packing',
                'is_packed',
                'is_done'
            ]);
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->index();
        });


    }
};
