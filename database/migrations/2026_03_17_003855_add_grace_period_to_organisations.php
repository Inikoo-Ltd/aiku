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
        Schema::table('organisations', function (Blueprint $table) {
            $table->unsignedInteger('late_grace_period_minutes')->default(15)->after('status');
        });
    }

    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('late_grace_period_minutes');
        });
    }
};
