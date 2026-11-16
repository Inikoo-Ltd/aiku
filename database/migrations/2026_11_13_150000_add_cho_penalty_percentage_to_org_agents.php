<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-13 15:00
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
            $table->decimal('cho_penalty_percentage', 5)->nullable()->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('org_agents', function (Blueprint $table) {
            $table->dropColumn('cho_penalty_percentage');
        });
    }
};
