<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('websites', function (Blueprint $table) {
            if (! Schema::hasColumn('websites', 'last_visited_at')) {
                $table->dateTimeTz('last_visited_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('websites', function (Blueprint $table) {
            if (Schema::hasColumn('websites', 'last_visited_at')) {
                $table->dropColumn(['last_visited_at']);
            }
        });
    }
};
