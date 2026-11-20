<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_files', function (Blueprint $table) {
            $table->string('disk')->nullable()->comment('set when the copy is too large for the media library and is kept on this disk instead');
            $table->string('path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('slack_files', function (Blueprint $table) {
            $table->dropColumn(['disk', 'path']);
        });
    }
};
