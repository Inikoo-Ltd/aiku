<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_subscribers', function (Blueprint $table) {
            $table->timestampTz('consented_at')->nullable();
            $table->jsonb('consent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_subscribers', function (Blueprint $table) {
            $table->dropColumn(['consented_at', 'consent']);
        });
    }
};
