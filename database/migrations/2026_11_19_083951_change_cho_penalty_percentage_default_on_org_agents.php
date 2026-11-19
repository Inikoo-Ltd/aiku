<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-19 16:39
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_agents', function (Blueprint $table) {
            $table->decimal('cho_penalty_percentage', 5)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('org_agents', function (Blueprint $table) {
            $table->decimal('cho_penalty_percentage', 5)->nullable()->default(1)->change();
        });
    }
};
