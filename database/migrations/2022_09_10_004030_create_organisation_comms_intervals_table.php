<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Nov 2024 13:13:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Stubs\Migrations\HasCommsIntervals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasCommsIntervals;

    public function up(): void
    {
        Schema::create('organisation_comms_intervals', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->getCommsIntervals($table);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('organisation_comms_intervals');
    }
};
