<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_events', function (Blueprint $table) {
            $table->unsignedInteger('chat_session_id')->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('chat_events', function (Blueprint $table) {
            $table->unsignedInteger('chat_session_id')->nullable(false)->change();
        });
    }
};
