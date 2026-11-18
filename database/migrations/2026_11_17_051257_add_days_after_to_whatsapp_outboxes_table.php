<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_outboxes', function (Blueprint $table) {
            $table->unsignedSmallInteger('days_after')->nullable()->comment('Days after the last invoice a Gold reward reminder is sent');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_outboxes', function (Blueprint $table) {
            $table->dropColumn('days_after');
        });
    }
};
