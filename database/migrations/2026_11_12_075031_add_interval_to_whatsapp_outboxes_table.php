<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_outboxes', function (Blueprint $table) {
            $table->unsignedSmallInteger('interval')->nullable()->comment('Hours between two bulk runs');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_outboxes', function (Blueprint $table) {
            $table->dropColumn('interval');
        });
    }
};
