<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 12:40:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('clockings', function (Blueprint $table) {
            $table->unsignedInteger('clocking_machine_qr_code_id')->nullable()->index();
            $table->foreign('clocking_machine_qr_code_id')->references('id')->on('clocking_machine_qr_codes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('clockings', function (Blueprint $table) {
            $table->dropForeign(['clocking_machine_qr_code_id']);
            $table->dropColumn('clocking_machine_qr_code_id');
        });
    }
};
