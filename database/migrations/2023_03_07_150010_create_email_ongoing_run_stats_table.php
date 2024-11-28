<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 08:14:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCommsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasCommsStats;
    public function up(): void
    {
        Schema::create('email_ongoing_run_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('email_ongoing_run_id')->nullable();
            $table->foreign('email_ongoing_run_id')->references('id')->on('email_ongoing_runs');
            $table = $this->dispatchedEmailStats($table);

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('email_ongoing_run_stats');
    }
};
