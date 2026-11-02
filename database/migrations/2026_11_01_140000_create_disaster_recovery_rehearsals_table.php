<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('disaster_recovery_rehearsals', function (Blueprint $table) {
            $table->id();
            $table->date('rehearsed_on')->index();
            $table->date('next_due_on');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::table('disaster_recovery_rehearsals')->insert([
            'rehearsed_on' => '2026-10-30',
            'next_due_on'  => '2027-01-15',
            'notes'        => 'INI-133: whole app on AWS EC2 Frankfurt from image aiku-dr-2026-10-30, database restored from S3 in 8 min 20 s, about 20 min from launch to working app.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('disaster_recovery_rehearsals');
    }
};
