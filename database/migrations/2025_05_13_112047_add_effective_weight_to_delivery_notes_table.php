<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 May 2025 19:21:04 Malaysia Time, plane KL-Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedInteger('weight')->nullable()->comment('actual weight, grams')->change();
            $table->unsignedInteger('estimated_weight')->default(0)->comment('grams');
            $table->unsignedInteger('effective_weight')->default(0)->index()
                ->comment('Used for UI tables (e.g. sorting), effective_weight=estimated_weight if weight is null, grams');

        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->unsignedInteger('estimated_required_weight')->default(0)->comment('grams');
            $table->unsignedInteger('estimated_picked_weight')->default(0)->comment('grams');

        });

    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('estimated_weight');
            $table->dropColumn('effective_weight');
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn('estimated_required_weight');
            $table->dropColumn('estimated_picked_weight');
        });
    }
};
