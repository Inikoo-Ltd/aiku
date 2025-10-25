<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 12:20:13 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            if (!Schema::hasColumn('trade_units', 'ufi_number')) {
                $table->string('ufi_number')->nullable();
            }
            if (!Schema::hasColumn('trade_units', 'scpn_number')) {
                $table->string('scpn_number')->nullable();
            }
        });
    }


    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn('ufi_number');
            $table->dropColumn('scpn_number');
        });
    }
};
