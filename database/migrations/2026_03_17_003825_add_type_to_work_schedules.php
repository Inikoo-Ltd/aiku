<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->enum('type', ['default', 'shift'])->default('default')->after('name');
        });
    }

    public function down()
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
