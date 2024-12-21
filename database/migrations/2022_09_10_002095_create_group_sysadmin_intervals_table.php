<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Dec 2024 14:56:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::create('group_sysadmin_intervals', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->unsignedIntegerDateIntervals($table, [
                'user_requests',
            ]);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('group_sysadmin_intervals');
    }
};
