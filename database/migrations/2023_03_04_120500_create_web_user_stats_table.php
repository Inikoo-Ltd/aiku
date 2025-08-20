<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 03:53:33 Mex Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasSysAdminStats;
use App\Stubs\Migrations\HasUserStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    use HasUserStats;
    use HasSysAdminStats;

    public function up(): void
    {
        Schema::create('web_user_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('web_user_id')->index();
            $table->foreign('web_user_id')->references('id')->on('web_users')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->userStats($table);
            $table = $this->auditFieldsForNonSystem($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_stats');
    }
};
