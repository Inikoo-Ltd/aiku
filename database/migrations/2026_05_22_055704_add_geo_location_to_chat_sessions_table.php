<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: YYYY-MM-DD HH:mm:ss
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->char('geo_country_code', 5)->nullable()->after('metadata');
            $table->unsignedBigInteger('website_visitor_id')->nullable()->index()->after('geo_country_code');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn(['geo_country_code', 'website_visitor_id']);
        });
    }
};
