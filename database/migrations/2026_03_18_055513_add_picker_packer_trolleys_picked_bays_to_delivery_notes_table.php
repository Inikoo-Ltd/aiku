<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Mar 2026 15:54:40 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('sort_picker')->nullable()->comment('Used only for UI tables sorting');
            $table->string('sort_packer')->nullable()->comment('Used only for UI tables sorting');
            $table->string('sort_trolleys')->nullable()->comment('Used only for UI tables sorting');
            $table->string('sort_picked_bays')->nullable()->comment('Used only for UI tables sorting');
        });
    }


    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn(['sort_picker', 'sort_packer', 'sort_trolleys', 'sort_picked_bays']);
        });
    }
};
