<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('organisation_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('organisation_stats', 'has_marketplace')) {
                $table->boolean('has_marketplace')->default(false);
            }
        });
    }

    public function down()
    {
        Schema::table('organisation_stats', function (Blueprint $table) {
            if (Schema::hasColumn('organisation_stats', 'has_marketplace')) {
                $table->dropColumn(['has_marketplace']);
            }
        });
    }
};
