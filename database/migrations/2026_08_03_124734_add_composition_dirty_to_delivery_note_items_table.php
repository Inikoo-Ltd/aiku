<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->timestampTz('composition_dirty_at')->nullable()
                ->comment('The SKU composition changed after picking work was done, a human must roll back or confirm');
            $table->decimal('composition_dirty_quantity_required', 16, 3)->nullable()
                ->comment('What quantity_required would be under the new composition');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn(['composition_dirty_at', 'composition_dirty_quantity_required']);
        });
    }
};
