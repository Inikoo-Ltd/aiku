<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clocking_machines', function (Blueprint $table) {
            if (!Schema::hasColumn('clocking_machines', 'config')) {
                $table->jsonb('config')->default(DB::raw("'{}'::jsonb"));
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clocking_machines', function (Blueprint $table) {
            if (Schema::hasColumn('clocking_machines', 'config')) {
                $table->dropColumn('config');
            }
        });
    }
};
