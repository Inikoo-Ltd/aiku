<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 09:10:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('clocking_machine_qr_codes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('clocking_machine_id')->index();
            $table->foreign('clocking_machine_id')->references('id')->on('clocking_machines')->cascadeOnDelete();
            $table->string('label')->nullable()->index();
            $table->string('hash', 8)->unique();
            $table->boolean('active')->default(true);
            $table->dateTimeTz('deactivated_at')->index()->nullable();
            $table->unsignedInteger('number_clockings')->default(0);
            $table->unsignedInteger('number_different_staff')->default(0);
            $table->dateTimeTz('last_used_at')->index()->nullable();

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('clocking_machine_qr_codes');
    }
};
