<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_user_stats', function (Blueprint $table) {
            $table->string('last_device')->nullable();
            $table->string('last_os')->nullable();
            $table->jsonb('last_location')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('web_user_stats', function (Blueprint $table) {
            $table->dropColumn('last_device');
            $table->dropColumn('last_os');
            $table->dropColumn('last_location');
        });
    }
};
