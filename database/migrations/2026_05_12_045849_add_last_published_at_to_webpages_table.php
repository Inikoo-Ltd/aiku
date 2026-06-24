<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('webpages', function (Blueprint $table) {
            if (! Schema::hasColumn('webpages', 'last_published_at')) {
                $table->dateTimeTz('last_published_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('webpages', function (Blueprint $table) {
            if (Schema::hasColumn('webpages', 'last_published_at')) {
                $table->dropColumn(['last_published_at']);
            }
        });
    }
};
