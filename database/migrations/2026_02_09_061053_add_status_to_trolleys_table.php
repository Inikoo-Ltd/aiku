<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:41:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trolleys', function (Blueprint $table) {
            $table->boolean('status')->default(true)->index();
            $table->unsignedInteger('current_delivery_note_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trolleys', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->unsignedInteger('current_delivery_note_id')->nullable(false)->change();
        });
    }
};
