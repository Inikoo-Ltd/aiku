<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 May 2025 12:06:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pickings', function (Blueprint $table) {
            $table->renameColumn('picker_id', 'picker_user_id');
            $table->dropColumn('state');
            $table->dropColumn('status');
            $table->dropColumn('quantity_required');
            $table->dropColumn('queued_at');
            $table->dropColumn('picking_at');
            $table->dropColumn('picking_blocked_at');
            $table->dropColumn('done_at');
            $table->renameColumn('quantity_picked', 'quantity');
            $table->string('type')->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('pickings', function (Blueprint $table) {
            $table->renameColumn('picker_user_id', 'picker_id');
            $table->string('state')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->integer('quantity_required')->nullable();
            $table->renameColumn('quantity', 'quantity_picked');
            $table->dropColumn('type');
            $table->timestampTz('queued_at');
            $table->timestampTz('picking_at');
            $table->timestampTz('picking_blocked_at');
            $table->timestampTz('done_at');
        });
    }
};
