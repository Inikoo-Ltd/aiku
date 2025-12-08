<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 14:59:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCRMStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCRMStats;

    public function up(): void
    {
        Schema::create('poll_option_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('poll_id')->index();
            $table->foreign('poll_id')->references('id')->on('polls');
            $table->unsignedSmallInteger('poll_option_id')->index();
            $table->foreign('poll_option_id')->references('id')->on('poll_options');
            $table = $this->customerStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_option_stats');
    }
};
