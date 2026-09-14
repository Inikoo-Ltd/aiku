<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('meta_chat_messages', function (Blueprint $table) {
            $table->unsignedInteger('whatsapp_campaign_id')->index()->nullable();
            $table->foreign('whatsapp_campaign_id')->references('id')->on('whatsapp_campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meta_chat_messages', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_campaign_id']);
            $table->dropColumn('whatsapp_campaign_id');
        });
    }
};
