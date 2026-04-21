<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_stats', function ($table) {
            $table->unsignedSmallInteger('number_webpages_families_overview')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('website_stats', function ($table) {
            $table->dropColumn(['number_webpages_families_overview']);
        });
    }
};
