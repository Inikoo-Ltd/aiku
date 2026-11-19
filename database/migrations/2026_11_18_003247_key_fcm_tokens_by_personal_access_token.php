<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->dropIndex(['token_id']);
            $table->dropColumn('token_id');
        });

        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->unsignedInteger('personal_access_token_id')->unique();
            $table->foreign('personal_access_token_id')->references('id')->on('personal_access_tokens')->cascadeOnDelete();
            $table->string('app_version', 32)->nullable();
            $table->unique('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->dropUnique(['fcm_token']);
            $table->dropForeign(['personal_access_token_id']);
            $table->dropUnique(['personal_access_token_id']);
            $table->dropColumn(['personal_access_token_id', 'app_version']);
        });

        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->string('token_id')->nullable()->index();
        });
    }
};
